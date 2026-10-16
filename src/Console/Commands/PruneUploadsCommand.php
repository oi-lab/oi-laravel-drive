<?php

namespace OiLab\OiLaravelDrive\Console\Commands;

use Illuminate\Console\Command;
use OiLab\OiLaravelDrive\Enums\DriveUploadStatus;
use OiLab\OiLaravelDrive\OiLaravelDrive;
use OiLab\OiLaravelDrive\Services\DriveUploads;

class PruneUploadsCommand extends Command
{
    protected $signature = 'drive:prune-uploads {--days=7 : Delete closed uploads older than this}';

    protected $description = 'Expire the drive upload links left without a file and delete old closed ones';

    public function handle(DriveUploads $uploads): int
    {
        $expired = $uploads->expireStale();

        $deleted = OiLaravelDrive::uploadModel()::query()
            ->where('status', '!=', DriveUploadStatus::Awaiting)
            ->where('updated_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("Expired {$expired} upload(s), deleted {$deleted} closed upload(s).");

        return self::SUCCESS;
    }
}
