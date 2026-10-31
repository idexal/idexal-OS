<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : How many dumps to retain}';

    protected $description = 'Write a compressed MySQL dump and prune older ones';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->run((int) $this->option('keep'));
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Wrote %s (%s), pruned %d.',
            $result['path'],
            number_format($result['size'] / 1024 / 1024, 1).' MB',
            $result['pruned'],
        ));

        return self::SUCCESS;
    }
}
