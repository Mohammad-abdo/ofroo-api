<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Mall;
use App\Models\Merchant;
use App\Models\Offer;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OfferSeeder extends Seeder
{
    /**
     * عناوين العروض حسب التصنيف (كل عرض يتبع تصنيفه منطقياً).
     * key = order_index من CategorySeeder (1=مولات, 2=مطاعم, ...).
     */
    private function getTitlesByCategory(): array
    {
        return [
            1 => [ // مولات Malls
                ['ar' => 'خصم 50% على المشتريات من المول', 'en' => '50% Off Mall Purchases'],
                ['ar' => 'عرض المول: خصم 30% على المتاجر المشاركة', 'en' => 'Mall Offer: 30% Off Participating Stores'],
                ['ar' => 'شراء اثنين واحصل على واحد مجاناً من المتجر', 'en' => 'Buy Two Get One Free at Store'],
                ['ar' => 'عرض العائلة في المول خصم 40%', 'en' => 'Family Mall Offer 40% Off'],
                ['ar' => 'خصم 20% على جميع المنتجات في المول', 'en' => '20% Off All Mall Products'],
                ['ar' => 'عرض نهاية الأسبوع في المول', 'en' => 'Weekend Mall Special'],
            ],
            2 => [ // مطاعم Restaurants
                ['ar' => 'خصم 25% على الوجبات', 'en' => '25% Off on Meals'],
                ['ar' => 'عرض العائلة: خصم 40% على المنيو', 'en' => 'Family Offer: 40% Off Menu'],
                ['ar' => 'وجبة مجانية عند شراء اثنتين', 'en' => 'Free Meal When You Buy Two'],
                ['ar' => 'عرض الإفطار خصم 30%', 'en' => 'Breakfast Offer 30% Off'],
                ['ar' => 'عرض نهاية الأسبوع للمطعم', 'en' => 'Weekend Restaurant Special'],
                ['ar' => 'خصم 20% على الطلبات لأكثر من 100 جنيه', 'en' => '20% Off Orders Over 100 EGP'],
            ],
            3 => [ // ترفيه Entertainment
                ['ar' => 'خصم 30% على تذاكر السينما', 'en' => '30% Off Cinema Tickets'],
                ['ar' => 'عرض العائلة للأنشطة الترفيهية', 'en' => 'Family Entertainment Package'],
                ['ar' => 'ادخل اثنين وادفع سعر واحد', 'en' => 'Buy One Get One Free Entry'],
                ['ar' => 'خصم 25% على ألعاب الملاهي', 'en' => '25% Off Amusement Rides'],
                ['ar' => 'عرض نهاية الأسبوع للترفيه', 'en' => 'Weekend Entertainment Offer'],
            ],
            4 => [ // صحة وجمال Health & Beauty
                ['ar' => 'خصم 40% على جلسات العناية', 'en' => '40% Off Care Sessions'],
                ['ar' => 'عرض الصالون: قصّة وتصفيف خصم 35%', 'en' => 'Salon Offer: Haircut & Styling 35% Off'],
                ['ar' => 'جلسة مساج أو عناية بالبشرة خصم 30%', 'en' => 'Massage or Skincare Session 30% Off'],
                ['ar' => 'خصم 25% على منتجات التجميل', 'en' => '25% Off Beauty Products'],
                ['ar' => 'عرض العافية خصم 20%', 'en' => 'Wellness Offer 20% Off'],
            ],
            5 => [ // أزياء Fashion
                ['ar' => 'خصم 35% على الملابس', 'en' => '35% Off on Clothing'],
                ['ar' => 'عرض الصيف على الأزياء خصم 50%', 'en' => 'Summer Fashion Sale 50% Off'],
                ['ar' => 'شراء قطعتين واحصل على خصم إضافي', 'en' => 'Buy Two Get Extra Discount'],
                ['ar' => 'خصم 30% على الأحذية والإكسسوارات', 'en' => '30% Off Shoes & Accessories'],
                ['ar' => 'عرض نهاية الموسم على الأزياء', 'en' => 'End of Season Fashion Sale'],
            ],
            6 => [ // إلكترونيات Electronics
                ['ar' => 'خصم 15% على الإلكترونيات', 'en' => '15% Off on Electronics'],
                ['ar' => 'عرض الهواتف والأجهزة الذكية خصم 20%', 'en' => 'Phones & Smart Devices 20% Off'],
                ['ar' => 'خصم 25% على الأجهزة المنزلية', 'en' => '25% Off Home Appliances'],
                ['ar' => 'عرض اللابتوب والإكسسوارات', 'en' => 'Laptop & Accessories Offer'],
                ['ar' => 'خصم 10% على الضمان الموسّع', 'en' => '10% Off Extended Warranty'],
            ],
            7 => [ // سيارات Automotive
                ['ar' => 'خصم 20% على تغيير الزيت والخدمة', 'en' => '20% Off Oil Change & Service'],
                ['ar' => 'عرض الصيانة الدورية خصم 25%', 'en' => 'Periodic Maintenance 25% Off'],
                ['ar' => 'غسيل وتلميع السيارة خصم 30%', 'en' => 'Car Wash & Polish 30% Off'],
                ['ar' => 'خصم 15% على قطع الغيار الأصلية', 'en' => '15% Off Original Spare Parts'],
                ['ar' => 'عرض فحص السيارة مجاناً مع أي خدمة', 'en' => 'Free Car Check with Any Service'],
            ],
            8 => [ // رياضة Sports
                ['ar' => 'خصم 30% على المعدات الرياضية', 'en' => '30% Off Sports Equipment'],
                ['ar' => 'عرض الاشتراك في النادي خصم 25%', 'en' => 'Gym Membership 25% Off'],
                ['ar' => 'ملابس وأحذية رياضية خصم 35%', 'en' => 'Sportswear & Shoes 35% Off'],
                ['ar' => 'عرض العائلة للأنشطة الرياضية', 'en' => 'Family Sports Activities Offer'],
                ['ar' => 'خصم 20% على المستلزمات الرياضية', 'en' => '20% Off Sports Supplies'],
            ],
            9 => [ // تعليم Education
                ['ar' => 'خصم 25% على الكورسات والدورات', 'en' => '25% Off Courses & Training'],
                ['ar' => 'عرض الاشتراك السنوي للمركز التعليمي', 'en' => 'Annual Education Center Subscription'],
                ['ar' => 'دورة مجانية عند التسجيل في اثنتين', 'en' => 'Free Course When You Enroll in Two'],
                ['ar' => 'خصم 30% على المواد والكتب الدراسية', 'en' => '30% Off Study Materials & Books'],
                ['ar' => 'عرض الطالب خصم 20%', 'en' => 'Student Offer 20% Off'],
            ],
            10 => [ // سفر Travel
                ['ar' => 'خصم 20% على حزم السفر', 'en' => '20% Off Travel Packages'],
                ['ar' => 'عرض الإقامة: ليلتين بسعر ليلة', 'en' => 'Stay Offer: Two Nights for One Price'],
                ['ar' => 'خصم 15% على تذاكر الطيران', 'en' => '15% Off Flight Tickets'],
                ['ar' => 'عرض العائلة للإجازة خصم 25%', 'en' => 'Family Vacation 25% Off'],
                ['ar' => 'رحلات يومية خصم 30%', 'en' => 'Day Trips 30% Off'],
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('ar_EG');
        $fakerEn = Faker::create('en_US');
        $categories = Category::whereNull('parent_id')->orderBy('order_index')->get();
        $merchants = Merchant::where('approved', true)->get();
        $titlesByCategory = $this->getTitlesByCategory();

        if ($categories->isEmpty() || $merchants->isEmpty()) {
            $this->command->warn('Categories or merchants missing. Run CategorySeeder and MerchantSeeder first.');

            return;
        }

        $offersToCreate = 100;
        $created = 0;

        while ($created < $offersToCreate) {
            foreach ($categories as $category) {
                if ($created >= $offersToCreate) {
                    break;
                }
                $titles = $titlesByCategory[$category->order_index] ?? $titlesByCategory[1];
                $merchant = $merchants->random();
                $branch = Branch::where('merchant_id', $merchant->id)->first();
                $title = $titles[array_rand($titles)];

                $originalPrice = $faker->randomFloat(2, 10, 500);
                $discountPercent = $faker->randomElement([10, 15, 20, 25, 30, 35, 40, 50]);
                $price = round($originalPrice * (1 - $discountPercent / 100), 2);
                $status = $faker->randomElement(['active', 'active', 'active', 'pending', 'expired', 'draft']);
                $startDate = Carbon::createFromInterface($faker->dateTimeBetween('-30 days', 'now'))->utc();
                $endDate = Carbon::createFromInterface($faker->dateTimeBetween('now', '+60 days'))->utc();
                $descMain = $faker->realText(300);
                $descAr = $faker->realText(200);
                $descEn = $fakerEn->text(200);

                // After migration 2026_02_01_111754: title_ar→title, description_ar→description (no title_ar / description_ar columns).
                $offerAttrs = [
                    'merchant_id' => $merchant->id,
                    'category_id' => $category->id,
                    'price' => $price,
                    'discount' => $discountPercent,
                    'offer_images' => [
                        $faker->randomElement([
                            'https://img.freepik.com/free-psd/super-sale-podium-product-banner-with-editable-text_47987-12084.jpg',
                            'https://img.freepik.com/free-psd/cyber-monday-facebook-template_23-2149839017.jpg',
                        ]),
                    ],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                ];
                if (Schema::hasColumn('offers', 'title_ar')) {
                    $offerAttrs['title_ar'] = $title['ar'];
                    $offerAttrs['title_en'] = $title['en'];
                    $offerAttrs['description_ar'] = $descAr;
                    $offerAttrs['description_en'] = $descEn;
                    if (Schema::hasColumn('offers', 'description')) {
                        $offerAttrs['description'] = $descMain;
                    }
                } else {
                    $offerAttrs['title'] = $title['ar'];
                    $offerAttrs['title_en'] = $title['en'];
                    $offerAttrs['description'] = $descMain;
                    $offerAttrs['description_en'] = $descEn;
                }

                if (Schema::hasColumn('offers', 'images') && ! Schema::hasColumn('offers', 'offer_images')) {
                    $offerAttrs['images'] = $offerAttrs['offer_images'];
                    unset($offerAttrs['offer_images']);
                }
                if (Schema::hasColumn('offers', 'start_at') && ! Schema::hasColumn('offers', 'start_date')) {
                    $offerAttrs['start_at'] = $startDate;
                    $offerAttrs['end_at'] = $endDate;
                    unset($offerAttrs['start_date'], $offerAttrs['end_date']);
                }

                if (Schema::hasColumn('offers', 'mall_id')) {
                    $resolvedMallId = $merchant->mall_id ?: ($branch?->mall_id ?? null);
                    if (! $resolvedMallId && Schema::hasColumn('merchants', 'category_id')) {
                        $mallCategory = $categories->firstWhere('name_ar', 'مولات');
                        if ($mallCategory && (int) $merchant->category_id === (int) $mallCategory->id) {
                            $resolvedMallId = Mall::query()
                                ->where('is_active', true)
                                ->inRandomOrder()
                                ->value('id');
                        }
                    }
                    if ($resolvedMallId) {
                        $offerAttrs['mall_id'] = (int) $resolvedMallId;
                    }
                }

                // forceCreate: legacy DB may use title_ar/description_ar columns not in $fillable
                $offer = Offer::forceCreate($offerAttrs);

                if ($branch) {
                    $offer->branches()->sync([$branch->id]);
                }

                // كل عرض بداخله عدة كوبونات (كما في الداشبورد: عرض ← كوبونات)
                $couponsCount = $faker->numberBetween(2, 5);
                for ($c = 0; $c < $couponsCount; $c++) {
                    $barcode = 'CPN-'.strtoupper($faker->unique()->bothify('########'));
                    $couponPayload = [
                        'title' => $title['ar'],
                        'description' => $faker->realText(150),
                        'price' => $price,
                        'discount' => $discountPercent,
                        'discount_type' => 'percent',
                        'barcode' => $barcode,
                        'coupon_code' => $barcode,
                        'expires_at' => $endDate,
                        'status' => 'active',
                    ];
                    if (Schema::hasColumn('coupons', 'category_id') && $offer->category_id) {
                        $couponPayload['category_id'] = $offer->category_id;
                    }
                    $offer->coupons()->create($couponPayload);
                }

                $created++;
            }
        }

        if (Schema::hasColumn('offers', 'mall_id')) {
            Offer::query()
                ->whereNull('mall_id')
                ->with(['merchant:id,mall_id', 'branches:id,mall_id'])
                ->chunkById(200, function ($offers): void {
                    foreach ($offers as $offer) {
                        $mid = $offer->merchant?->mall_id;
                        if (! $mid && $offer->relationLoaded('branches')) {
                            $mid = $offer->branches->first()?->mall_id;
                        }
                        if ($mid) {
                            $offer->forceFill(['mall_id' => (int) $mid])->saveQuietly();
                        }
                    }
                });
        }
    }
}
