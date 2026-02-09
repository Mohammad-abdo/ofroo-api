<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\StoreLocation;
use Illuminate\Support\Facades\Hash;

// Get merchant role
$merchantRole = Role::where('name', 'merchant')->first();

if (!$merchantRole) {
    echo "Merchant role not found. Please run RoleSeeder first.\n";
    exit(1);
}

// Check if test merchant already exists
$existingMerchant = User::where('email', 'trader@test.com')->first();
if ($existingMerchant) {
    echo "Test merchant already exists!\n";
    echo "Email: trader@test.com\n";
    echo "Password: password\n";
    exit(0);
}

// Create test merchant user
$merchantUser = User::create([
    'name' => 'Ahmed Mohamed Al-Ali',
    'name_ar' => 'أحمد محمد العلي',
    'email' => 'trader@test.com',
    'phone' => '+201234567890',
    'password' => Hash::make('password'),
    'language' => 'ar',
    'role_id' => $merchantRole->id,
    'email_verified_at' => now(),
    'city' => 'القاهرة',
    'country' => 'مصر',
]);

// Create merchant profile
$merchant = Merchant::create([
    'user_id' => $merchantUser->id,
    'company_name' => 'Al-Asala Egyptian Restaurant',
    'company_name_ar' => 'مطعم الأصالة المصري',
    'company_name_en' => 'Al-Asala Egyptian Restaurant',
    'description' => 'A restaurant specializing in popular Egyptian dishes and authentic Arab cuisine. We offer the finest quality food with the highest standards of quality and cleanliness.',
    'description_ar' => 'مطعم متخصص في الأكلات المصرية الشعبية والأطباق العربية الأصيلة، تقدم أجود المأكولات بأعلى معايير الجودة والنظافة.',
    'description_en' => 'A restaurant specializing in popular Egyptian dishes and authentic Arab cuisine. We offer the finest quality food with the highest standards of quality and cleanliness.',
    'address' => 'Nasr City, City Stars Mall, Second Floor, Shop No. 245',
    'address_ar' => 'مدينة نصر، سيتي ستارز الدور الثاني، محل رقم 245',
    'address_en' => 'Nasr City, City Stars Mall, Second Floor, Shop No. 245',
    'phone' => '+201234567890',
    'whatsapp_link' => 'https://wa.me/201234567890',
    'city' => 'القاهرة',
    'country' => 'مصر',
    'approved' => true,
]);

// Create store location
StoreLocation::create([
    'merchant_id' => $merchant->id,
    'lat' => 30.0626,
    'lng' => 31.3219,
    'address' => 'Nasr City, City Stars Mall, Second Floor, Shop No. 245',
    'address_ar' => 'مدينة نصر، سيتي ستارز الدور الثاني، محل رقم 245',
    'address_en' => 'Nasr City, City Stars Mall, Second Floor, Shop No. 245',
    'google_place_id' => 'ChIJTestPlace123456789',
    'opening_hours' => [
        'monday' => '10:00-22:00',
        'tuesday' => '10:00-22:00',
        'wednesday' => '10:00-22:00',
        'thursday' => '10:00-22:00',
        'friday' => '14:00-22:00',
        'saturday' => '10:00-22:00',
        'sunday' => '10:00-22:00',
    ],
]);

echo "✅ Test merchant created successfully!\n\n";
echo "📧 Email: trader@test.com\n";
echo "🔑 Password: password\n\n";
echo "Merchant Details:\n";
echo "- Name: Ahmed Mohamed Al-Ali (أحمد محمد العلي)\n";
echo "- Company: Al-Asala Egyptian Restaurant (مطعم الأصالة المصري)\n";
echo "- Status: Approved\n";



