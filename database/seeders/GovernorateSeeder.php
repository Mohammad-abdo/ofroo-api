<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    /**
     * Seed all 27 Egyptian governorates and cities (see database/data/egypt_governorates_cities.php).
     */
    public function run(): void
    {
        /** @var array<int, array{name_ar: string, name_en: string, order_index: int, cities: array<int, array{name_ar: string, name_en: string, order_index: int}>}> */
        $governorates = require database_path('data/egypt_governorates_cities.php');

        foreach ($governorates as $g) {
            $gov = Governorate::query()->updateOrCreate(
                ['name_ar' => $g['name_ar']],
                [
                    'name_en' => $g['name_en'],
                    'order_index' => $g['order_index'],
                ]
            );

            foreach ($g['cities'] as $c) {
                City::query()->updateOrCreate(
                    [
                        'governorate_id' => $gov->id,
                        'name_ar' => $c['name_ar'],
                    ],
                    [
                        'name_en' => $c['name_en'],
                        'order_index' => $c['order_index'],
                    ]
                );
            }
        }
    }
}
