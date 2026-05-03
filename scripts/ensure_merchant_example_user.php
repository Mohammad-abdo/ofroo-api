<?php

/**
 * Dev helper: ensure merchant6@example.com exists (password: password).
 * MerchantSeeder can fail when merchants table omits legacy columns (company_name, etc.).
 *
 * Usage: php scripts/ensure_merchant_example_user.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

$email = 'merchant6@example.com';
$plain = 'password';

$merchantRole = Role::where('name', 'merchant')->first();
if (! $merchantRole) {
    fwrite(STDERR, "merchant role missing — run RoleSeeder.\n");
    exit(1);
}

$user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

if (! $user) {
    $user = User::create([
        'name' => 'Merchant Six (seed)',
        'email' => $email,
        'phone' => '+201000000006',
        'password' => Hash::make($plain),
        'language' => 'ar',
        'role_id' => $merchantRole->id,
        'email_verified_at' => now(),
        'country' => 'مصر',
    ]);
    echo "Created user id={$user->id}\n";
} else {
    $user->password = Hash::make($plain);
    $user->role_id = $merchantRole->id;
    $user->save();
    echo "Updated user id={$user->id} (password reset to password)\n";
}

if (! Merchant::where('user_id', $user->id)->exists()) {
    $cols = Schema::getColumnListing('merchants');
    $categoryId = Schema::hasColumn('merchants', 'category_id')
        ? Category::query()->value('id')
        : null;

    $row = [
        'user_id' => $user->id,
        'approved' => true,
        'country' => 'مصر',
        'city' => 'القاهرة',
        'phone' => $user->phone,
        'whatsapp_enabled' => false,
    ];

    if (Schema::hasColumn('merchants', 'company_name')) {
        $row['company_name'] = 'Merchant Six';
    }
    if (Schema::hasColumn('merchants', 'company_name_ar')) {
        $row['company_name_ar'] = 'تاجر ستة';
    }
    if (Schema::hasColumn('merchants', 'company_name_en')) {
        $row['company_name_en'] = 'Merchant Six';
    }
    if (Schema::hasColumn('merchants', 'status')) {
        $row['status'] = 'active';
    }
    if ($categoryId && in_array('category_id', $cols, true)) {
        $row['category_id'] = $categoryId;
    }

    $row = array_intersect_key($row, array_flip($cols));

    Merchant::query()->insert(array_merge($row, [
        'created_at' => now(),
        'updated_at' => now(),
    ]));
    echo "Created merchant row for user_id={$user->id}\n";
} else {
    echo "Merchant row already exists.\n";
}

echo "OK — login with {$email} / {$plain}\n";
