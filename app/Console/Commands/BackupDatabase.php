<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a daily database backup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting database backup...');

        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $path = storage_path('app/backups/' . $filename);

        // Create backups directory if it doesn't exist
        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s > %s',
            escapeshellarg($host),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($path)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->info("Database backup created successfully: {$filename}");
            
            // Keep only last 7 days of backups
            $this->cleanOldBackups();
            
            return Command::SUCCESS;
        } else {
            $this->error('Database backup failed!');
            return Command::FAILURE;
        }
    }

    /**
     * Clean old backups (keep only last 7 days)
     */
    protected function cleanOldBackups(): void
    {
        $backupPath = storage_path('app/backups');
        $files = glob($backupPath . '/backup_*.sql');
        
        // Sort by modification time
        usort($files, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Keep only last 7 files
        if (count($files) > 7) {
            $filesToDelete = array_slice($files, 0, count($files) - 7);
            foreach ($filesToDelete as $file) {
                unlink($file);
                $this->info("Deleted old backup: " . basename($file));
            }
        }
    }
}

