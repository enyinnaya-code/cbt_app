<?php

namespace App\Services;

use App\Models\Paper;
use App\Models\Topic;
use App\Support\HtmlCleaner;

/**
 * Reads a CSV of questions for one paper. It checks every row first and reports every problem with its line
 * number, so an examiner can fix the file in one go. Nothing is saved unless every row is valid.
 *
 * Columns (header row required, any order, case does not matter):
 *   question*, option_a*, option_b*, option_c, option_d, option_e, answer*, marks, topic, explanation_en, explanation_pcm
 *   type: "question" (default) or "passage" (then only "question" is used: the reading text or instruction).
 * Plain text is wrapped in <p>; a cell that starts with "<" is treated as HTML. All of it is sanitised.
 */
class QuestionImporter
{
    public const MAX_ROWS = 1000;
    private const LETTERS = ['a', 'b', 'c', 'd', 'e'];

    public static function sample(): string
    {
        return implode("\r\n", [
            'type,question,option_a,option_b,option_c,option_d,option_e,answer,marks,topic,explanation_en,explanation_pcm',
            'passage,"Read the passage and answer questions 1 and 2. Although it was raining, the children insisted on playing outside.",,,,,,,,,,',
            'question,"What does the word ""insisted"" mean?",Forced someone,Requested politely,Demanded firmly,Suggested gently,,C,1,Vocabulary,"Insisted means demanded firmly.","Insisted mean say na so e go be."',
            'question,Which of these is a prime number?,4,6,7,9,,C,1,,,',
        ]) . "\r\n";
    }

    /**
     * @return array{rows: array<int, array<string,mixed>>, errors: array<int, string>}
     */
    public function parse(string $path, Paper $paper): array
    {
        $raw = (string) file_get_contents($path);
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        // Excel's plain "CSV" is Windows-1252, not UTF-8. Convert so accents and naira signs survive.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $raw);
        rewind($stream);

        $header = fgetcsv($stream);
        if (! $header || $header === [null]) {
            return ['rows' => [], 'errors' => [1 => 'The file is empty.']];
        }
        $header = array_map(fn ($c) => strtolower(trim((string) $c)), $header);

        foreach (['question', 'option_a', 'option_b', 'answer'] as $need) {
            if (! in_array($need, $header, true)) {
                return ['rows' => [], 'errors' => [1 => "The header row must include the column \"$need\". See the sample file."]];
            }
        }

        $topics = Topic::where('subject_id', $paper->subject_id)->get()->keyBy(fn ($t) => mb_strtolower($t->name));
        $rows = [];
        $errors = [];
        $line = 1;

        while (($cells = fgetcsv($stream)) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) { continue; }

            if (count($rows) + count($errors) >= self::MAX_ROWS) {
                $errors[$line] = 'Too many rows. Import at most ' . self::MAX_ROWS . ' at a time.';
                break;
            }

            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
            $result = $this->row($row, $topics);

            is_string($result) ? $errors[$line] = $result : $rows[$line] = $result;
        }

        fclose($stream);

        if (! $rows && ! $errors) { $errors[1] = 'The file has a header but no questions.'; }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** @return array<string,mixed>|string a row of column values, or an error message */
    private function row(array $r, $topics): array|string
    {
        $get = fn (string $k) => trim((string) ($r[$k] ?? ''));

        $text = $this->html($get('question'));
        if (! $this->hasContent($text)) { return 'The question text is empty.'; }

        $type = strtolower($get('type') ?: 'question');
        if ($type === 'passage') {
            return ['not_question' => 1, 'question' => $text, 'options' => null, 'answer' => null, 'mark' => null,
                'topic_id' => null, 'explanation_en' => null, 'explanation_pcm' => null];
        }
        if ($type !== 'question') { return "Unknown type \"$type\". Use question or passage."; }

        $options = [];
        foreach (self::LETTERS as $l) {
            $clean = $this->html($get("option_$l"));
            if ($this->hasContent($clean)) { $options[strtoupper($l)] = $clean; }
        }
        if (count($options) < 2) { return 'At least two options are needed (option_a and option_b).'; }

        $answer = strtoupper($get('answer'));
        if (! isset($options[$answer])) { return "The answer \"{$get('answer')}\" is not one of the filled-in options."; }

        $marks = $get('marks') === '' ? 1 : $get('marks');
        if (! ctype_digit((string) $marks) || (int) $marks < 1 || (int) $marks > 20) { return "Marks must be a whole number from 1 to 20, not \"$marks\"."; }

        $topicId = null;
        if ($get('topic') !== '') {
            $topic = $topics->get(mb_strtolower($get('topic')));
            if (! $topic) { return "Unknown topic \"{$get('topic')}\". Add it under Topics first, or leave the cell empty."; }
            $topicId = $topic->id;
        }

        return [
            'not_question' => 0,
            'question' => $text,
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'answer' => $answer,
            'mark' => (int) $marks,
            'topic_id' => $topicId,
            'explanation_en' => $this->optionalHtml($get('explanation_en')),
            'explanation_pcm' => $this->optionalHtml($get('explanation_pcm')),
        ];
    }

    private function html(string $cell): string
    {
        if ($cell === '') { return ''; }

        // A cell that looks like HTML is kept as HTML; anything else is text, so "x < 5" is not mistaken for a tag.
        $looksLikeHtml = str_starts_with($cell, '<') && preg_match('/<\/?[a-z][a-z0-9]*[\s>\/]/i', $cell);

        return HtmlCleaner::clean($looksLikeHtml ? $cell : '<p>' . nl2br(e($cell), false) . '</p>');
    }

    private function optionalHtml(string $cell): ?string
    {
        $html = $this->html($cell);

        return $this->hasContent($html) ? $html : null;
    }

    private function hasContent(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") !== '' || str_contains($html, '<img');
    }
}
