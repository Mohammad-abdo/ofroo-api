<?php

/**
 * Creates a minimal merchants row for users who have the merchant role but no merchant profile.
 * Without this, CheckMerchant returns "Merchant access required." after a successful login.
 *
 * Usage: php scripts/repair_merchant_portal_users.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

$merchantRole = Role::where('name', 'merchant')->first();
if (! $merchantRole) {
    fwrite(STDERR, "merchant role missing.\n");
    exit(1);
}

$categoryId = Schema::hasColumn('merchants', 'category_id')
    ? Category::query()->value('id')
    : null;

$cols = Schema::getColumnListing('merchants');
$fixed = 0;

User::query()
    ->where('role_id', $merchantRole->id)
    ->whereDoesntHave('merchant')
    ->orderBy('id')
    ->each(function (User $user) use ($categoryId, $cols, &$fixed) {
        $row = [
            'user_id' => $user->id,
            'approved' => true,
            'country' => $user->country ?: 'مصر',
            'city' => is_string($user->city) ? $user->city : 'القاهرة',
            'phone' => $user->phone,
            'whatsapp_enabled' => false,
        ];

        if (Schema::hasColumn('merchants', 'company_name')) {
            $row['company_name'] = $user->name;
        }
        if (Schema::hasColumn('merchants', 'company_name_ar')) {
            $row['company_name_ar'] = $user->name;
        }
        if (Schema::hasColumn('merchants', 'company_name_en')) {
            $row['company_name_en'] = $user->name;
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

        echo "merchant profile created for user_id={$user->id} ({$user->email})\n";
        $fixed++;
    });

echo $fixed === 0 ? "Nothing to repair.\n" : "Done. Repaired {$fixed} user(s).\n";
