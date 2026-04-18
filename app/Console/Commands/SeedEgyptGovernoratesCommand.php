<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Governorate;
use Database\Seeders\GovernorateSeeder;
use Illuminate\Console\Command;

class SeedEgyptGovernoratesCommand extends Command
{
    protected $signature = 'db:seed-egypt-locations';

    protected $description = 'Seed all Egyptian governorates and cities (GovernorateSeeder).';

    public function handle(): int
    {
        $this->info('Seeding governorates & cities from database/data/egypt_governorates_cities.php …');
        $this->getLaravel()->make(GovernorateSeeder::class)->run();
        $this->info('Done. Governorates: '.Governorate::query()->count().', cities: '.City::query()->count());

        return self::SUCCESS;
    }
}
