<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Mall;
use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FillCityForeignKeys extends Command
{
    protected $signature = 'ofroo:fill-city-foreign-keys
                            {--dry-run : Show counts without saving}';

    protected $description = 'Match malls.city and merchants.city text to cities.name_ar / name_en and set city_id';

    public function handle(): int
    {
        if (! Schema::hasColumn('malls', 'city_id') || ! Schema::hasColumn('merchants', 'city_id')) {
            $this->error('Run migrations: city_id columns missing.');

            return self::FAILURE;
        }

        $cities = City::query()->get(['id', 'name_ar', 'name_en']);
        $lookup = [];
        foreach ($cities as $city) {
            foreach ([$city->name_ar, $city->name_en] as $label) {
                $k = $this->norm($label);
                if ($k !== '') {
                    $lookup[$k] = (int) $city->id;
                }
            }
        }

        $dry = (bool) $this->option('dry-run');
        $mallUpdated = 0;
        $merchantUpdated = 0;

        Mall::query()->whereNull('city_id')->whereNotNull('city')->chunkById(100, function ($rows) use ($lookup, $dry, &$mallUpdated) {
            foreach ($rows as $mall) {
                $id = $lookup[$this->norm($mall->city)] ?? null;
                if ($id === null) {
                    continue;
                }
                if (! $dry) {
                    $mall->update(['city_id' => $id]);
                }
                $mallUpdated++;
            }
        });

        Merchant::query()->whereNull('city_id')->whereNotNull('city')->chunkById(100, function ($rows) use ($lookup, $dry, &$merchantUpdated) {
            foreach ($rows as $merchant) {
                $id = $lookup[$this->norm($merchant->city)] ?? null;
                if ($id === null) {
                    continue;
                }
                if (! $dry) {
                    $merchant->update(['city_id' => $id]);
                }
                $merchantUpdated++;
            }
        });

        $this->info($dry ? '[dry-run] rows matched (not saved):' : 'Updated rows:');
        $this->line("  malls: {$mallUpdated}");
        $this->line("  merchants: {$merchantUpdated}");

        return self::SUCCESS;
    }

    protected function norm(?string $s): string
    {
        $s = trim((string) $s);

        return Str::lower(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
