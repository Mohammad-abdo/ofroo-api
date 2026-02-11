<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Database\Seeder;

class GovernorateSeeder extends Seeder
{
    /**
     * Seed governorates and cities for Egypt (محافظات ومدن مصر).
     */
    public function run(): void
    {
        $governorates = [
            ['name_ar' => 'القاهرة', 'name_en' => 'Cairo', 'order_index' => 1, 'cities' => [
                ['name_ar' => 'المعادي', 'name_en' => 'Maadi', 'order_index' => 1],
                ['name_ar' => 'مدينة نصر', 'name_en' => 'Nasr City', 'order_index' => 2],
                ['name_ar' => 'الزمالك', 'name_en' => 'Zamalek', 'order_index' => 3],
                ['name_ar' => 'الدقي', 'name_en' => 'Dokki', 'order_index' => 4],
                ['name_ar' => 'المقطم', 'name_en' => 'Mokattam', 'order_index' => 5],
                ['name_ar' => 'الزاوية الحمراء', 'name_en' => 'Zawya El-Hamra', 'order_index' => 6],
                ['name_ar' => 'شبرا', 'name_en' => 'Shubra', 'order_index' => 7],
                ['name_ar' => 'السلام', 'name_en' => 'Al Salam', 'order_index' => 8],
                ['name_ar' => 'عين شمس', 'name_en' => 'Ain Shams', 'order_index' => 9],
                ['name_ar' => 'المرج', 'name_en' => 'Al Marg', 'order_index' => 10],
            ]],
            ['name_ar' => 'الجيزة', 'name_en' => 'Giza', 'order_index' => 2, 'cities' => [
                ['name_ar' => 'الشيخ زايد', 'name_en' => 'Sheikh Zayed', 'order_index' => 1],
                ['name_ar' => '6 أكتوبر', 'name_en' => '6th of October', 'order_index' => 2],
                ['name_ar' => 'الهرم', 'name_en' => 'Haram', 'order_index' => 3],
                ['name_ar' => 'فيصل', 'name_en' => 'Faisal', 'order_index' => 4],
                ['name_ar' => 'الدقي', 'name_en' => 'Dokki', 'order_index' => 5],
                ['name_ar' => 'المحافظة', 'name_en' => 'Giza City', 'order_index' => 6],
                ['name_ar' => 'العمرانية', 'name_en' => 'Omraniya', 'order_index' => 7],
                ['name_ar' => 'البدرشين', 'name_en' => 'Badrasheen', 'order_index' => 8],
                ['name_ar' => 'الوراق', 'name_en' => 'Warraq', 'order_index' => 9],
            ]],
            ['name_ar' => 'الإسكندرية', 'name_en' => 'Alexandria', 'order_index' => 3, 'cities' => [
                ['name_ar' => 'المندرة', 'name_en' => 'Mandara', 'order_index' => 1],
                ['name_ar' => 'سموحة', 'name_en' => 'Smouha', 'order_index' => 2],
                ['name_ar' => 'ستانلي', 'name_en' => 'Stanley', 'order_index' => 3],
                ['name_ar' => 'سيدي جابر', 'name_en' => 'Sidi Gaber', 'order_index' => 4],
                ['name_ar' => 'الرمل', 'name_en' => 'Al Raml', 'order_index' => 5],
                ['name_ar' => 'العصافرة', 'name_en' => 'Agamy', 'order_index' => 6],
                ['name_ar' => 'برج العرب', 'name_en' => 'Borg El Arab', 'order_index' => 7],
                ['name_ar' => 'المعمورة', 'name_en' => 'Al Montazah', 'order_index' => 8],
                ['name_ar' => 'المنتزه', 'name_en' => 'Montaza', 'order_index' => 9],
            ]],
            ['name_ar' => 'الدقهلية', 'name_en' => 'Dakahlia', 'order_index' => 4, 'cities' => [
                ['name_ar' => 'المنصورة', 'name_en' => 'Mansoura', 'order_index' => 1],
                ['name_ar' => 'طلخا', 'name_en' => 'Talkha', 'order_index' => 2],
                ['name_ar' => 'ميت غمر', 'name_en' => 'Mit Ghamr', 'order_index' => 3],
                ['name_ar' => 'بلقاس', 'name_en' => 'Belqas', 'order_index' => 4],
                ['name_ar' => 'دكرنس', 'name_en' => 'Dekernes', 'order_index' => 5],
                ['name_ar' => 'أجا', 'name_en' => 'Aga', 'order_index' => 6],
                ['name_ar' => 'منية النصر', 'name_en' => 'Menia El Nasr', 'order_index' => 7],
                ['name_ar' => 'شربين', 'name_en' => 'Sherbin', 'order_index' => 8],
            ]],
            ['name_ar' => 'الشرقية', 'name_en' => 'Sharqia', 'order_index' => 5, 'cities' => [
                ['name_ar' => 'الزقازيق', 'name_en' => 'Zagazig', 'order_index' => 1],
                ['name_ar' => 'العاشر من رمضان', 'name_en' => '10th of Ramadan', 'order_index' => 2],
                ['name_ar' => 'بلبيس', 'name_en' => 'Belbeis', 'order_index' => 3],
                ['name_ar' => 'فاقوس', 'name_en' => 'Faquos', 'order_index' => 4],
                ['name_ar' => 'منيا القمح', 'name_en' => 'Minyat Al Qamh', 'order_index' => 5],
                ['name_ar' => 'ههيا', 'name_en' => 'Hehya', 'order_index' => 6],
                ['name_ar' => 'أبو حماد', 'name_en' => 'Abu Hammad', 'order_index' => 7],
            ]],
            ['name_ar' => 'القليوبية', 'name_en' => 'Qalyubia', 'order_index' => 6, 'cities' => [
                ['name_ar' => 'بنها', 'name_en' => 'Banha', 'order_index' => 1],
                ['name_ar' => 'شبرا الخيمة', 'name_en' => 'Shubra El Kheima', 'order_index' => 2],
                ['name_ar' => 'القناطر الخيرية', 'name_en' => 'Qalyub', 'order_index' => 3],
                ['name_ar' => 'الخانكة', 'name_en' => 'Khanka', 'order_index' => 4],
                ['name_ar' => 'قليوب', 'name_en' => 'Qalyub', 'order_index' => 5],
                ['name_ar' => 'طوخ', 'name_en' => 'Toukh', 'order_index' => 6],
            ]],
            ['name_ar' => 'الغربية', 'name_en' => 'Gharbia', 'order_index' => 7, 'cities' => [
                ['name_ar' => 'طنطا', 'name_en' => 'Tanta', 'order_index' => 1],
                ['name_ar' => 'المحلة الكبرى', 'name_en' => 'Mahalla', 'order_index' => 2],
                ['name_ar' => 'كفر الزيات', 'name_en' => 'Kafr El Zayat', 'order_index' => 3],
                ['name_ar' => 'زفتى', 'name_en' => 'Zefta', 'order_index' => 4],
                ['name_ar' => 'سنورس', 'name_en' => 'Samannud', 'order_index' => 5],
                ['name_ar' => 'بسيون', 'name_en' => 'Basyoun', 'order_index' => 6],
            ]],
            ['name_ar' => 'المنوفية', 'name_en' => 'Menoufia', 'order_index' => 8, 'cities' => [
                ['name_ar' => 'شبين الكوم', 'name_en' => 'Shibin El Kom', 'order_index' => 1],
                ['name_ar' => 'منوف', 'name_en' => 'Menouf', 'order_index' => 2],
                ['name_ar' => 'أشمون', 'name_en' => 'Ashmoun', 'order_index' => 3],
                ['name_ar' => 'قويسنا', 'name_en' => 'Quesna', 'order_index' => 4],
                ['name_ar' => 'الباجور', 'name_en' => 'Al Bagour', 'order_index' => 5],
            ]],
            ['name_ar' => 'البحيرة', 'name_en' => 'Beheira', 'order_index' => 9, 'cities' => [
                ['name_ar' => 'دمنهور', 'name_en' => 'Damanhur', 'order_index' => 1],
                ['name_ar' => 'كفر الدوار', 'name_en' => 'Kafr El Dawar', 'order_index' => 2],
                ['name_ar' => 'رشيد', 'name_en' => 'Rashid', 'order_index' => 3],
                ['name_ar' => 'إدكو', 'name_en' => 'Edku', 'order_index' => 4],
                ['name_ar' => 'كوم حمادة', 'name_en' => 'Kom Hamada', 'order_index' => 5],
                ['name_ar' => 'وادي النطرون', 'name_en' => 'Wadi El Natrun', 'order_index' => 6],
            ]],
            ['name_ar' => 'الإسماعيلية', 'name_en' => 'Ismailia', 'order_index' => 10, 'cities' => [
                ['name_ar' => 'الإسماعيلية', 'name_en' => 'Ismailia', 'order_index' => 1],
                ['name_ar' => 'فايد', 'name_en' => 'Fayed', 'order_index' => 2],
                ['name_ar' => 'القنطرة', 'name_en' => 'Qantara', 'order_index' => 3],
                ['name_ar' => 'التل الكبير', 'name_en' => 'Tell El Kebir', 'order_index' => 4],
                ['name_ar' => 'أبو صوير', 'name_en' => 'Abu Suweir', 'order_index' => 5],
            ]],
            ['name_ar' => 'بورسعيد', 'name_en' => 'Port Said', 'order_index' => 11, 'cities' => [
                ['name_ar' => 'بورسعيد', 'name_en' => 'Port Said', 'order_index' => 1],
                ['name_ar' => 'بورفؤاد', 'name_en' => 'Port Fuad', 'order_index' => 2],
            ]],
            ['name_ar' => 'السويس', 'name_en' => 'Suez', 'order_index' => 12, 'cities' => [
                ['name_ar' => 'السويس', 'name_en' => 'Suez', 'order_index' => 1],
                ['name_ar' => 'عتاقة', 'name_en' => 'Ataka', 'order_index' => 2],
                ['name_ar' => 'الجناين', 'name_en' => 'Ganayen', 'order_index' => 3],
            ]],
            ['name_ar' => 'الفيوم', 'name_en' => 'Fayoum', 'order_index' => 13, 'cities' => [
                ['name_ar' => 'الفيوم', 'name_en' => 'Fayoum', 'order_index' => 1],
                ['name_ar' => 'طامية', 'name_en' => 'Tamiya', 'order_index' => 2],
                ['name_ar' => 'سنورس', 'name_en' => 'Senouras', 'order_index' => 3],
                ['name_ar' => 'إطسا', 'name_en' => 'Itsa', 'order_index' => 4],
                ['name_ar' => 'يوسف الصديق', 'name_en' => 'Yusuf El Sadiq', 'order_index' => 5],
            ]],
            ['name_ar' => 'بني سويف', 'name_en' => 'Beni Suef', 'order_index' => 14, 'cities' => [
                ['name_ar' => 'بني سويف', 'name_en' => 'Beni Suef', 'order_index' => 1],
                ['name_ar' => 'الواسطى', 'name_en' => 'Al Wasta', 'order_index' => 2],
                ['name_ar' => 'ناصر', 'name_en' => 'Naser', 'order_index' => 3],
                ['name_ar' => 'إهناسيا', 'name_en' => 'Ehnasia', 'order_index' => 4],
                ['name_ar' => 'ببا', 'name_en' => 'Biba', 'order_index' => 5],
            ]],
            ['name_ar' => 'المنيا', 'name_en' => 'Minya', 'order_index' => 15, 'cities' => [
                ['name_ar' => 'المنيا', 'name_en' => 'Minya', 'order_index' => 1],
                ['name_ar' => 'ملوي', 'name_en' => 'Malawi', 'order_index' => 2],
                ['name_ar' => 'سمالوط', 'name_en' => 'Samalout', 'order_index' => 3],
                ['name_ar' => 'مغاغة', 'name_en' => 'Maghagha', 'order_index' => 4],
                ['name_ar' => 'بني مزار', 'name_en' => 'Beni Mazar', 'order_index' => 5],
            ]],
            ['name_ar' => 'أسيوط', 'name_en' => 'Assiut', 'order_index' => 16, 'cities' => [
                ['name_ar' => 'أسيوط', 'name_en' => 'Assiut', 'order_index' => 1],
                ['name_ar' => 'ديروط', 'name_en' => 'Dayrout', 'order_index' => 2],
                ['name_ar' => 'منفلوط', 'name_en' => 'Manfalout', 'order_index' => 3],
                ['name_ar' => 'أبنوب', 'name_en' => 'Abnub', 'order_index' => 4],
                ['name_ar' => 'صدفا', 'name_en' => 'Sodfa', 'order_index' => 5],
            ]],
            ['name_ar' => 'سوهاج', 'name_en' => 'Sohag', 'order_index' => 17, 'cities' => [
                ['name_ar' => 'سوهاج', 'name_en' => 'Sohag', 'order_index' => 1],
                ['name_ar' => 'جرجا', 'name_en' => 'Gerga', 'order_index' => 2],
                ['name_ar' => 'أخميم', 'name_en' => 'Akhmim', 'order_index' => 3],
                ['name_ar' => 'البلينا', 'name_en' => 'Al Balyana', 'order_index' => 4],
                ['name_ar' => 'دار السلام', 'name_en' => 'Dar El Salam', 'order_index' => 5],
            ]],
            ['name_ar' => 'قنا', 'name_en' => 'Qena', 'order_index' => 18, 'cities' => [
                ['name_ar' => 'قنا', 'name_en' => 'Qena', 'order_index' => 1],
                ['name_ar' => 'قفط', 'name_en' => 'Qift', 'order_index' => 2],
                ['name_ar' => 'نجع حمادي', 'name_en' => 'Nag Hammadi', 'order_index' => 3],
                ['name_ar' => 'دشنا', 'name_en' => 'Deshna', 'order_index' => 4],
                ['name_ar' => 'وقف', 'name_en' => 'Waqf', 'order_index' => 5],
            ]],
            ['name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'order_index' => 19, 'cities' => [
                ['name_ar' => 'الأقصر', 'name_en' => 'Luxor', 'order_index' => 1],
                ['name_ar' => 'إسنا', 'name_en' => 'Esna', 'order_index' => 2],
                ['name_ar' => 'الطود', 'name_en' => 'Al Tod', 'order_index' => 3],
            ]],
            ['name_ar' => 'أسوان', 'name_en' => 'Aswan', 'order_index' => 20, 'cities' => [
                ['name_ar' => 'أسوان', 'name_en' => 'Aswan', 'order_index' => 1],
                ['name_ar' => 'كوم أمبو', 'name_en' => 'Kom Ombo', 'order_index' => 2],
                ['name_ar' => 'دراو', 'name_en' => 'Daraw', 'order_index' => 3],
                ['name_ar' => 'نصر النوبة', 'name_en' => 'Nasr El Nuba', 'order_index' => 4],
                ['name_ar' => 'إدفو', 'name_en' => 'Edfu', 'order_index' => 5],
            ]],
            ['name_ar' => 'البحر الأحمر', 'name_en' => 'Red Sea', 'order_index' => 21, 'cities' => [
                ['name_ar' => 'الغردقة', 'name_en' => 'Hurghada', 'order_index' => 1],
                ['name_ar' => 'مرسى علم', 'name_en' => 'Marsa Alam', 'order_index' => 2],
                ['name_ar' => 'رأس غارب', 'name_en' => 'Ras Ghareb', 'order_index' => 3],
                ['name_ar' => 'سفاجا', 'name_en' => 'Safaga', 'order_index' => 4],
                ['name_ar' => 'القصير', 'name_en' => 'El Qoseir', 'order_index' => 5],
                ['name_ar' => 'شلاتين', 'name_en' => 'Halayeb', 'order_index' => 6],
            ]],
            ['name_ar' => 'الوادي الجديد', 'name_en' => 'New Valley', 'order_index' => 22, 'cities' => [
                ['name_ar' => 'الخارجة', 'name_en' => 'Kharga', 'order_index' => 1],
                ['name_ar' => 'الداخلة', 'name_en' => 'Dakhla', 'order_index' => 2],
                ['name_ar' => 'الفرافرة', 'name_en' => 'Farafra', 'order_index' => 3],
                ['name_ar' => 'باريس', 'name_en' => 'Baris', 'order_index' => 4],
            ]],
            ['name_ar' => 'مطروح', 'name_en' => 'Matrouh', 'order_index' => 23, 'cities' => [
                ['name_ar' => 'مرسى مطروح', 'name_en' => 'Marsa Matrouh', 'order_index' => 1],
                ['name_ar' => 'الحمام', 'name_en' => 'Hammam', 'order_index' => 2],
                ['name_ar' => 'السلوم', 'name_en' => 'Sallum', 'order_index' => 3],
                ['name_ar' => 'سيوة', 'name_en' => 'Siwa', 'order_index' => 4],
                ['name_ar' => 'النجيلة', 'name_en' => 'El Negaila', 'order_index' => 5],
            ]],
            ['name_ar' => 'شمال سيناء', 'name_en' => 'North Sinai', 'order_index' => 24, 'cities' => [
                ['name_ar' => 'العريش', 'name_en' => 'Arish', 'order_index' => 1],
                ['name_ar' => 'الشيخ زويد', 'name_en' => 'Sheikh Zuweid', 'order_index' => 2],
                ['name_ar' => 'رفح', 'name_en' => 'Rafah', 'order_index' => 3],
                ['name_ar' => 'بئر العبد', 'name_en' => 'Bir Al Abd', 'order_index' => 4],
            ]],
            ['name_ar' => 'جنوب سيناء', 'name_en' => 'South Sinai', 'order_index' => 25, 'cities' => [
                ['name_ar' => 'الطور', 'name_en' => 'El Tor', 'order_index' => 1],
                ['name_ar' => 'شرم الشيخ', 'name_en' => 'Sharm El Sheikh', 'order_index' => 2],
                ['name_ar' => 'دهب', 'name_en' => 'Dahab', 'order_index' => 3],
                ['name_ar' => 'نويبع', 'name_en' => 'Nuweiba', 'order_index' => 4],
                ['name_ar' => 'سانت كاترين', 'name_en' => 'Saint Catherine', 'order_index' => 5],
            ]],
            ['name_ar' => 'كفر الشيخ', 'name_en' => 'Kafr El Sheikh', 'order_index' => 26, 'cities' => [
                ['name_ar' => 'كفر الشيخ', 'name_en' => 'Kafr El Sheikh', 'order_index' => 1],
                ['name_ar' => 'دسوق', 'name_en' => 'Desouk', 'order_index' => 2],
                ['name_ar' => 'فوه', 'name_en' => 'Fuwa', 'order_index' => 3],
                ['name_ar' => 'بلطيم', 'name_en' => 'Baltim', 'order_index' => 4],
                ['name_ar' => 'سيدي سالم', 'name_en' => 'Sidi Salim', 'order_index' => 5],
            ]],
            ['name_ar' => 'دمياط', 'name_en' => 'Damietta', 'order_index' => 27, 'cities' => [
                ['name_ar' => 'دمياط', 'name_en' => 'Damietta', 'order_index' => 1],
                ['name_ar' => 'رأس البر', 'name_en' => 'Ras El Bar', 'order_index' => 2],
                ['name_ar' => 'فارسكور', 'name_en' => 'Faraskour', 'order_index' => 3],
                ['name_ar' => 'الزرقا', 'name_en' => 'Zarqa', 'order_index' => 4],
                ['name_ar' => 'كفر سعد', 'name_en' => 'Kafr Saad', 'order_index' => 5],
            ]],
        ];

        foreach ($governorates as $g) {
            $gov = Governorate::firstOrCreate(
                ['name_ar' => $g['name_ar']],
                [
                    'name_en' => $g['name_en'],
                    'order_index' => $g['order_index'],
                ]
            );
            foreach ($g['cities'] as $c) {
                City::firstOrCreate(
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
