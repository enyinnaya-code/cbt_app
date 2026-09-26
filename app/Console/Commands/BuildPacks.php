<?php

namespace App\Console\Commands;

use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Subject;
use App\Services\PackBuilder;
use Illuminate\Console\Command;

class BuildPacks extends Command
{
    protected $signature = 'testacbt:build-packs
        {--exam= : Only this exam (slug, e.g. waec)}
        {--subject= : Only this subject (slug)}
        {--force : Write a new version even if nothing changed}';

    protected $description = 'Build the offline content packs from published papers';

    public function handle(PackBuilder $builder): int
    {
        $filter = fn ($q) => $q
            ->when($this->option('exam'), fn ($q, $slug) => $q->whereIn('exam_id', Exam::where('slug', $slug)->pluck('id')))
            ->when($this->option('subject'), fn ($q, $slug) => $q->whereIn('subject_id', Subject::where('slug', $slug)->pluck('id')))
            ->select('exam_id', 'subject_id')->distinct();

        // Include packs that already exist too, so unpublishing every paper of a subject retires its pack.
        $pairs = $filter(Paper::published())->get()
            ->concat($filter(ContentPack::current())->get())
            ->unique(fn ($p) => $p->exam_id . '-' . $p->subject_id)
            ->values();

        if ($pairs->isEmpty()) {
            $this->warn('No published papers match. Publish some first: php artisan testacbt:publish-papers --all');
            return self::SUCCESS;
        }

        foreach ($pairs as $pair) {
            $exam = Exam::find($pair->exam_id);
            $subject = Subject::find($pair->subject_id);
            $result = $builder->build($exam, $subject, (bool) $this->option('force'));

            $label = "{$exam->name} / {$subject->name}";
            $pack = $result['pack'];

            match ($result['status']) {
                'built' => $this->info(sprintf('%-40s v%d  %d papers, %d questions, %s', $label, $pack->version, $pack->paper_count, $pack->question_count, $this->size($pack->size_bytes))),
                'unchanged' => $this->line(sprintf('%-40s v%d  unchanged', $label, $pack->version)),
                default => $this->line("$label  {$result['status']}"),
            };

            foreach ($result['warnings'] as $w) {
                $this->warn("    $w");
            }
        }

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
    }
}
