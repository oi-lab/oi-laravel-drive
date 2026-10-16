<?php

namespace OiLab\OiLaravelDrive\Console\Commands;

use Illuminate\Console\Command;
use OiLab\OiLaravelDrive\Services\DriveManager;

class PurgeTrashCommand extends Command
{
    protected $signature = 'drive:purge-trash {--days= : Age in days (default: trash.purge_after_days)}';

    protected $description = 'Delete for good the drive items left in the trash for too long';

    public function handle(DriveManager $drives): int
    {
        $days = $this->option('days') ?? config('oi-laravel-drive.trash.purge_after_days');

        if ($days === null || $days === '') {
            $this->info('No retention set (oi-laravel-drive.trash.purge_after_days): nothing to purge.');

            return self::SUCCESS;
        }

        $count = $drives->purgeTrash((int) $days);
        $this->info("Purged {$count} item(s) trashed more than {$days} day(s) ago.");

        return self::SUCCESS;
    }
}
