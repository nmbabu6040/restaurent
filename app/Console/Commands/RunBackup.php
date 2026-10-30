<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunBackup extends Command
{
    protected $signature = 'backup:run {--no-uploads : Skip the photos archive}';

    protected $description = 'Back up the database and the uploaded photos';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create(! $this->option('no-uploads'));
        } catch (\Throwable $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Database: ' . basename($result['db']));

        if ($result['uploads']) {
            $this->info('Photos: ' . basename($result['uploads']));
        }

        return self::SUCCESS;
    }
}
