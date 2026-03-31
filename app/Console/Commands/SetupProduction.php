<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SetupProduction extends Command
{
    protected $signature = 'setup:production';
    protected $description = 'Setup production environment - create storage link and optimize';

    public function handle(): int
    {
        $this->info('Setting up production environment...');

        // 1. Create storage link
        $this->info('Creating storage link...');
        if (File::exists(public_path('storage'))) {
            $this->warn('Storage link already exists.');
        } else {
            Artisan::call('storage:link');
            $this->info('Storage link created successfully.');
        }

        // 2. Clear caches
        $this->info('Clearing caches...');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('cache:clear');

        // 3. Optimize
        $this->info('Optimizing application...');
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('optimize:clear');
        Artisan::call('optimize');

        $this->newLine();
        $this->info('✅ Production setup complete!');
        $this->info('Storage URL: ' . url('/storage'));
        
        return Command::SUCCESS;
    }
}
