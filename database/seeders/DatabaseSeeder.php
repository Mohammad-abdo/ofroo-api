<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var list<class-string<Seeder>> */
    protected array $foundationSeeders = [
        GovernorateSeeder::class,
        PermissionSeeder::class,
        RoleSeeder::class,
        UserSeeder::class,
        AdminStaffSeeder::class,
        CategorySeeder::class,
        MallSeeder::class,
        MerchantSeeder::class,
        SettingsSeeder::class,
    ];

    /** @var list<class-string<Seeder>> */
    protected array $demoSeeders = [
        OfferSeeder::class,
        CouponSeeder::class,
        OrderSeeder::class,
        CartSeeder::class,
        FinancialSeeder::class,
        WalletSeeder::class,
        ReviewSeeder::class,
        LoyaltySeeder::class,
        SupportSeeder::class,
        MerchantStaffSeeder::class,
        AdSeeder::class,
        WarningSeeder::class,
        NotificationSeeder::class,
        ActivityLogSeeder::class,
    ];

    public function run(): void
    {
        $this->call($this->foundationSeeders);

        if (! config('seeding.run_demo_seeders')) {
            $this->command?->info('SEED_DEMO=false: skipped demo seeders (offers, orders, reviews, …).');

            return;
        }

        if ($this->shouldSkipDemoBecauseAlreadyCompleted()) {
            $this->command?->warn(
                'Demo data already seeded — skipped duplicate-prone seeders. '.
                'Use migrate:fresh --seed for a clean DB, or set SEED_FORCE=true (duplicates rows), or SEED_SKIP_DEMO_IF_DONE=false.'
            );

            return;
        }

        $this->call($this->demoSeeders);
        $this->markDemoSeedCompleted();
    }

    protected function shouldSkipDemoBecauseAlreadyCompleted(): bool
    {
        if (! config('seeding.skip_demo_if_already_completed')) {
            return false;
        }

        if (config('seeding.force_demo_repeat')) {
            return false;
        }

        if (! Schema::hasTable('settings')) {
            return false;
        }

        return (bool) Setting::getValue('seed_demo_completed', false);
    }

    protected function markDemoSeedCompleted(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        Setting::setValue('seed_demo_completed', true, 'boolean');
    }
}
