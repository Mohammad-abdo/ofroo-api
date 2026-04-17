<?php

namespace Database\Seeders;

use App\Models\Mall;
use Illuminate\Database\Seeder;

class MallSeeder extends Seeder
{
    /**
     * Ten well-known shopping malls in Egypt (Cairo, Giza, Alexandria).
     */
    public function run(): void
    {
        $placeholderImage = 'https://images.unsplash.com/photo-1567449303079-42e023d31f32?w=800&q=80';

        $malls = [
            [
                'name_en' => 'Citystars Heliopolis',
                'name_ar' => 'ستايرز مصر الجديدة',
                'city' => 'القاهرة',
                'address_ar' => 'شارع عمر بن الخطاب، مصر الجديدة، القاهرة',
                'address_en' => 'Omar Ibn El Khattab St., Heliopolis, Cairo',
                'latitude' => 30.0882,
                'longitude' => 31.3451,
                'phone' => '+20224193000',
                'website' => 'https://www.citystars.com.eg',
            ],
            [
                'name_en' => 'Cairo Festival City Mall',
                'name_ar' => 'كايرو فستيفال سيتي مول',
                'city' => 'القاهرة الجديدة',
                'address_ar' => 'التجمع الخامس، القاهرة الجديدة',
                'address_en' => '5th Settlement, New Cairo',
                'latitude' => 30.0307,
                'longitude' => 31.4994,
                'phone' => '+20226100000',
                'website' => 'https://www.cfc.com.eg',
            ],
            [
                'name_en' => 'Mall of Arabia',
                'name_ar' => 'مول العرب',
                'city' => 'الجيزة',
                'address_ar' => 'مدينة 6 أكتوبر، الجيزة',
                'address_en' => '6th of October City, Giza',
                'latitude' => 29.9697,
                'longitude' => 30.9564,
                'phone' => '+20238500000',
                'website' => 'https://www.mallofarabia.com.eg',
            ],
            [
                'name_en' => 'Mall of Egypt',
                'name_ar' => 'مول مصر',
                'city' => 'الجيزة',
                'address_ar' => 'طريق الواحات، 6 أكتوبر، الجيزة',
                'address_en' => 'Wahat Road, 6th October, Giza',
                'latitude' => 29.9711,
                'longitude' => 31.0033,
                'phone' => '+20235350000',
                'website' => 'https://www.mallofegypt.com',
            ],
            [
                'name_en' => 'Point 90 Mall',
                'name_ar' => 'بوينت 90',
                'city' => 'القاهرة الجديدة',
                'address_ar' => 'التجمع الخامس، القاهرة الجديدة',
                'address_en' => '5th Settlement, New Cairo',
                'latitude' => 30.0208,
                'longitude' => 31.4984,
                'phone' => '+20226180000',
                'website' => 'https://www.point90egypt.com',
            ],
            [
                'name_en' => 'City Centre Almaza',
                'name_ar' => 'سيتي سنتر المعز',
                'city' => 'القاهرة',
                'address_ar' => 'طريق النصر، المعز، مصر الجديدة',
                'address_en' => 'Nasr Road, Almaza, Heliopolis, Cairo',
                'latitude' => 30.0934,
                'longitude' => 31.3212,
                'phone' => '+20224170000',
                'website' => 'https://www.majidalfuttaim.com',
            ],
            [
                'name_en' => 'Dandy Mega Mall',
                'name_ar' => 'داندي ميجا مول',
                'city' => 'الإسكندرية',
                'address_ar' => 'سموحة، الإسكندرية',
                'address_en' => 'Smouha, Alexandria',
                'latitude' => 31.2156,
                'longitude' => 29.9532,
                'phone' => '+2035410000',
                'website' => 'https://www.dandymall.com.eg',
            ],
            [
                'name_en' => 'San Stefano Grand Plaza',
                'name_ar' => 'سان ستيفانو جراند بلازا',
                'city' => 'الإسكندرية',
                'address_ar' => 'كورنيش الإسكندرية، سان ستيفانو',
                'address_en' => 'Alexandria Corniche, San Stefano',
                'latitude' => 31.2458,
                'longitude' => 29.9654,
                'phone' => '+2035810000',
                'website' => 'https://www.sanstefano.com.eg',
            ],
            [
                'name_en' => 'Arkan Plaza',
                'name_ar' => 'أركان بلازا',
                'city' => 'الجيزة',
                'address_ar' => 'الشيخ زايد، الجيزة',
                'address_en' => 'Sheikh Zayed, Giza',
                'latitude' => 30.0737,
                'longitude' => 30.9766,
                'phone' => '+20238580000',
                'website' => 'https://www.arkan.com.eg',
            ],
            [
                'name_en' => 'Genena Mall',
                'name_ar' => 'جنينة مول',
                'city' => 'القاهرة',
                'address_ar' => 'مدينة نصر، القاهرة',
                'address_en' => 'Nasr City, Cairo',
                'latitude' => 30.0508,
                'longitude' => 31.3449,
                'phone' => '+20224040000',
                'website' => 'https://www.genenamall.com',
            ],
        ];

        foreach ($malls as $index => $row) {
            $name = $row['name_ar'] ?? $row['name_en'];
            $address = $row['address_ar'] ?? $row['address_en'];

            Mall::updateOrCreate(
                ['name_en' => $row['name_en']],
                [
                    'name' => $name,
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'],
                    'description_ar' => 'مجمع تجاري رائد في ' . $row['city'] . '، مصر.',
                    'description_en' => 'A major shopping and entertainment mall in Egypt.',
                    'description' => $row['name_en'] . ' — Egypt.',
                    'address' => $address,
                    'address_ar' => $row['address_ar'],
                    'address_en' => $row['address_en'],
                    'city' => $row['city'],
                    'country' => 'مصر',
                    'latitude' => $row['latitude'],
                    'longitude' => $row['longitude'],
                    'phone' => $row['phone'],
                    'website' => $row['website'],
                    'email' => null,
                    'image_url' => $placeholderImage,
                    'opening_hours' => [
                        'ar' => '10:00 - 22:00',
                        'en' => '10:00 - 22:00',
                    ],
                    'is_active' => true,
                    'order_index' => $index,
                ]
            );
        }

        $this->command->info('Seeded 10 malls (Egypt).');
    }
}
