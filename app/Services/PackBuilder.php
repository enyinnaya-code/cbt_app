<?php

namespace App\Services;

use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Support\HtmlCleaner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the downloadable offline packs: for each exam + subject, one gzip-compressed JSON file with every published
 * paper (the "full" pack) and a small one with only the free sample (the "free" pack, for students who have not
 * unlocked the subject). A new version is only written when the content changed.
 */
class PackBuilder
{
    public function __construct(private QuestionSelector $selector) {}

    public const FORMAT = 1;
    private const MAX_INLINE_IMAGE_BYTES = 300 * 1024;
    private const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    /** @var array<string, int> counters for the build report */
    public array $stats = [];

    /**
     * Builds the full pack and then the free one. The result describes the full pack.
     *
     * @return array{status:string, pack:?ContentPack, warnings:array<int,string>}
     */
    public function build(Exam $exam, Subject $subject, bool $force = false): array
    {
        $papers = $this->papers($exam, $subject);

        $result = $this->publish($exam, $subject, ContentPack::FULL, $papers, null, $force);
        $this->publishFree($exam, $subject, $papers, $force);

        return $result;
    }

    /** Rebuilds only the free pack. Used when a price or the number of free questions changes. */
    public function buildFree(Exam $exam, Subject $subject, bool $force = false): string
    {
        return $this->publishFree($exam, $subject, $this->papers($exam, $subject), $force);
    }

    /** @return \Illuminate\Support\Collection<int, Paper> */
    private function papers(Exam $exam, Subject $subject)
    {
        return Paper::published()
            ->where('exam_id', $exam->id)->where('subject_id', $subject->id)
            ->whereHas('questions')
            ->orderByDesc('year')->orderBy('id')
            ->with(['questions' => fn ($q) => $q->orderBy('id')])
            ->get();
    }

    /** The free pack exists only while the subject is paid and has a free sample to give. */
    private function publishFree(Exam $exam, Subject $subject, $papers, bool $force): string
    {
        $pivot = Pricing::pivot($exam, $subject);
        $limit = Pricing::freeQuestions($pivot);
        $free = (Pricing::price($pivot) > 0 && $limit > 0 && $papers->isNotEmpty())
            ? $this->selector->freeIds($exam->id, $subject->id, $limit)
            : [];

        return $this->publish($exam, $subject, ContentPack::FREE, $free ? $papers : collect(), $free, $force)['status'];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Paper>  $papers
     * @param  array<int,int>|null  $onlyIds  for the free pack: the ids of the questions to include
     * @return array{status:string, pack:?ContentPack, warnings:array<int,string>}
     */
    private function publish(Exam $exam, Subject $subject, string $tier, $papers, ?array $onlyIds, bool $force): array
    {
        $this->stats = ['skipped_no_answer' => 0, 'images_inlined' => 0, 'images_missing' => 0, 'images_too_big' => 0];

        $current = ContentPack::current()->tier($tier)->where('exam_id', $exam->id)->where('subject_id', $subject->id)->first();
        $wanted = $onlyIds === null ? null : array_flip($onlyIds);

        if ($papers->isEmpty()) {
            // Nothing published any more: retire the pack so it leaves the catalog.
            if ($current) {
                $current->update(['is_current' => false]);
                return ['status' => 'retired', 'pack' => $current, 'warnings' => []];
            }
            return ['status' => 'empty', 'pack' => null, 'warnings' => []];
        }

        $display = $exam->subjects()->whereKey($subject->id)->first()?->pivot->display_name;
        $topicIds = [];

        $paperData = $papers->map(function (Paper $paper) use (&$topicIds, $wanted) {
            $items = [];
            $pending = null;   // the latest passage or instruction, kept only if a question after it is kept

            foreach ($paper->questions as $q) {
                $item = $this->item($q);
                if ($item === null) { continue; }

                if ($wanted !== null) {
                    if ($item['type'] === 'instruction') { $pending = $item; continue; }
                    if (! isset($wanted[$item['id']])) { continue; }
                    if ($pending !== null) { $items[] = $pending; $pending = null; }
                }

                if (! empty($item['topic_id'])) { $topicIds[$item['topic_id']] = true; }
                $items[] = $item;
            }
            return [
                'id' => $paper->id,
                'year' => $paper->year,
                'title' => $paper->title,
                'duration_minutes' => $paper->duration_minutes,
                'items' => $items,
            ];
        })->filter(fn ($p) => collect($p['items'])->contains(fn ($i) => $i['type'] === 'mcq'))->values();

        $topics = $subject->topics()->whereIn('id', array_keys($topicIds))->orderBy('name')->get(['id', 'name'])->toArray();

        $content = [
            'exam' => ['slug' => $exam->slug, 'name' => $exam->name],
            'subject' => ['slug' => $subject->slug, 'name' => $subject->name, 'display_name' => $display ?: $subject->name],
            'topics' => $topics,
            'papers' => $paperData->all(),
        ];

        $contentHash = hash('sha256', $this->encode($content));

        if (! $force && $current && $current->content_hash === $contentHash) {
            return ['status' => 'unchanged', 'pack' => $current, 'warnings' => $this->warnings()];
        }

        $version = ((int) ContentPack::tier($tier)->where('exam_id', $exam->id)->where('subject_id', $subject->id)->max('version')) + 1;
        $payload = ['format' => self::FORMAT, 'tier' => $tier, 'version' => $version, 'generated_at' => now()->toIso8601String()] + $content;
        $gz = gzencode($this->encode($payload), 9);

        $path = "packs/{$exam->slug}-{$subject->slug}" . ($tier === ContentPack::FULL ? '' : "-{$tier}") . "-v{$version}.json.gz";
        Storage::disk('local')->put($path, $gz);

        $questionCount = $paperData->sum(fn ($p) => collect($p['items'])->where('type', 'mcq')->count());

        $pack = DB::transaction(function () use ($exam, $subject, $tier, $version, $path, $gz, $contentHash, $paperData, $questionCount) {
            ContentPack::tier($tier)->where('exam_id', $exam->id)->where('subject_id', $subject->id)->update(['is_current' => false]);

            return ContentPack::create([
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'tier' => $tier,
                'version' => $version,
                'is_current' => true,
                'path' => $path,
                'size_bytes' => strlen($gz),
                'sha256' => hash('sha256', $gz),
                'content_hash' => $contentHash,
                'paper_count' => $paperData->count(),
                'question_count' => $questionCount,
                'years' => $paperData->pluck('year')->unique()->sort()->values()->all(),
                'built_at' => now(),
            ]);
        });

        $this->prune($exam, $subject, $tier);

        return ['status' => 'built', 'pack' => $pack, 'warnings' => $this->warnings()];
    }

    /** Keep the current and previous version on disk (a phone may still be mid-download); delete the rest. */
    private function prune(Exam $exam, Subject $subject, string $tier): void
    {
        $old = ContentPack::tier($tier)->where('exam_id', $exam->id)->where('subject_id', $subject->id)
            ->orderByDesc('version')->get()->slice(2);

        foreach ($old as $pack) {
            Storage::disk('local')->delete($pack->path);
            $pack->delete();
        }
    }

    private function item(Question $q): ?array
    {
        if ((int) $q->not_question === 1) {
            return ['id' => $q->id, 'type' => 'instruction', 'html' => $this->clean($q->question)];
        }

        $options = is_array($q->options) ? $q->options : json_decode((string) $q->options, true);
        $answer = strtoupper(trim((string) $q->answer));

        if (! is_array($options) || $answer === '' || ! array_key_exists($answer, $options)) {
            $this->stats['skipped_no_answer']++;   // would be unscoreable on the phone
            return null;
        }

        return [
            'id' => $q->id,
            'type' => 'mcq',
            'html' => $this->clean($q->question),
            'options' => array_map(fn ($o) => $this->clean((string) $o), array_filter($options, fn ($o) => $o !== null && $o !== '')),
            'answer' => $answer,
            'marks' => (int) ($q->mark ?: 1),
            'topic_id' => $q->topic_id,
            'explanation_en' => $q->explanation_en,
            'explanation_pcm' => $q->explanation_pcm,
        ];
    }

    /** Sanitise the HTML, then embed locally uploaded images so the pack works with no network. */
    private function clean(string $html): string
    {
        $html = HtmlCleaner::clean($html);

        return preg_replace_callback('/(<img\b[^>]*?\bsrc\s*=\s*)(["\'])(.*?)\2/i', function ($m) {
            $url = html_entity_decode($m[3]);
            if (str_starts_with($url, 'data:')) { return $m[0]; }

            $path = parse_url($url, PHP_URL_PATH) ?: '';
            if (! preg_match('#/uploads/([A-Za-z0-9._-]+)$#', $path, $f)) { return $m[0]; }

            $file = public_path('uploads/' . $f[1]);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! is_file($file) || ! isset(self::IMAGE_TYPES[$ext])) { $this->stats['images_missing']++; return $m[0]; }
            if (filesize($file) > self::MAX_INLINE_IMAGE_BYTES) { $this->stats['images_too_big']++; return $m[0]; }

            $this->stats['images_inlined']++;
            return $m[1] . $m[2] . 'data:' . self::IMAGE_TYPES[$ext] . ';base64,' . base64_encode(file_get_contents($file)) . $m[2];
        }, $html);
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** @return array<int,string> */
    private function warnings(): array
    {
        $w = [];
        if ($this->stats['skipped_no_answer']) { $w[] = "{$this->stats['skipped_no_answer']} question(s) skipped: no valid answer key"; }
        if ($this->stats['images_missing']) { $w[] = "{$this->stats['images_missing']} image(s) not found on disk, left as online links"; }
        if ($this->stats['images_too_big']) { $w[] = "{$this->stats['images_too_big']} image(s) over 300 KB, left as online links"; }
        return $w;
    }
}
