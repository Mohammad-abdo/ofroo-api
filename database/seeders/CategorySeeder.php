<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name_ar' => 'مولات',
                'name_en' => 'Malls',
                'order_index' => 1,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'مطاعم',
                'name_en' => 'Restaurants',
                'order_index' => 2,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'ترفيه',
                'name_en' => 'Entertainment',
                'order_index' => 3,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'صحة وجمال',
                'name_en' => 'Health & Beauty',
                'order_index' => 4,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'أزياء',
                'name_en' => 'Fashion',
                'order_index' => 5,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'إلكترونيات',
                'name_en' => 'Electronics',
                'order_index' => 6,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'سيارات',
                'name_en' => 'Automotive',
                'order_index' => 7,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'رياضة',
                'name_en' => 'Sports',
                'order_index' => 8,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'تعليم',
                'name_en' => 'Education',
                'order_index' => 9,
                'parent_id' => null,
                'image' => null,
            ],
            [
                'name_ar' => 'سفر',
                'name_en' => 'Travel',
                'order_index' => 10,
                'parent_id' => null,
                'image' => null,
            ],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['name_ar' => $category['name_ar']],
                $category
            );
        }
    }
}
