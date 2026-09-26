<?php

namespace App\Console\Commands;

use App\Models\Paper;
use Illuminate\Console\Command;

class PublishPapers extends Command
{
    protected $signature = 'testacbt:publish-papers
        {ids?* : Paper ids to change}
        {--all : Every paper that is currently a draft (or, with --unpublish, every published one)}
        {--unpublish : Move papers back to draft}
        {--build : Rebuild the offline packs afterwards}';

    protected $description = 'Publish papers (or move them back to draft) so students can download them';

    public function handle(): int
    {
        $unpublish = (bool) $this->option('unpublish');
        $from = $unpublish ? Paper::PUBLISHED : Paper::DRAFT;

        $query = Paper::where('status', $from);

        if ($this->option('all')) {
            // no id filter
        } elseif ($ids = $this->argument('ids')) {
            $query->whereIn('id', $ids);
        } else {
            $this->error('Give paper ids, or --all.');
            return self::FAILURE;
        }

        $count = $query->count();

        $query->update($unpublish
            ? ['status' => Paper::DRAFT, 'published_at' => null]
            : ['status' => Paper::PUBLISHED, 'published_at' => now()]);

        $this->info(($unpublish ? 'Moved to draft: ' : 'Published: ') . $count . ' paper(s).');

        if ($this->option('build')) {
            $this->call('testacbt:build-packs');
        } elseif ($count) {
            $this->line('Run php artisan testacbt:build-packs to update the downloadable packs.');
        }

        return self::SUCCESS;
    }
}
