<?php

namespace App\Console\Commands;

use App\Jobs\SendOtpPhoneNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class TestRegisterAndSendOtpCommand extends Command
{
    protected $signature = 'ofroo:test-register-otp
        {phone=01120916853 : Phone number (same format as mobile app / DB)}
        {--sync : Send OTP job immediately (no queue worker)}
        {--register-only : Only ensure user exists; do not generate OTP or notify}';

    protected $description = 'Create a test mobile user for the given phone (if missing) and send an OTP SMS/WhatsApp (same flow as /api/mobile/auth/otp/request).';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');
        $digitsOnly = preg_replace('/\D/', '', $phone);

        if (strlen($digitsOnly) < 8) {
            $this->error('Phone must contain at least 8 digits.');

            return self::FAILURE;
        }

        $userRole = Role::query()->where('name', 'user')->first();
        if (! $userRole) {
            $this->error('Role "user" not found. Run database seeders.');

            return self::FAILURE;
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            $email = sprintf('otp_test_%s@ofroo.test', $digitsOnly);
            if (User::query()->where('email', $email)->exists()) {
                $this->error("Email {$email} is already taken by another account. Delete or change that user, or use a different phone.");

                return self::FAILURE;
            }

            $user = User::query()->create([
                'name' => 'OTP Test User',
                'email' => $email,
                'phone' => $phone,
                'password' => 'TestOtpRegister123!',
                'language' => 'ar',
                'role_id' => $userRole->id,
                'country' => 'مصر',
            ]);

            $this->info("Created user id={$user->id} phone={$phone} email={$email}");
            $this->line('Login password for API tests: TestOtpRegister123!');
        } else {
            $this->info("Using existing user id={$user->id} phone={$phone}");
        }

        if ($this->option('register-only')) {
            $this->info('Skipped OTP (--register-only).');

            return self::SUCCESS;
        }

        if (config('otp.test_mode')) {
            $otp = (string) config('otp.test_code', '123456');
        } else {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }

        $user->update([
            'otp_code' => $otp,
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->line("OTP stored (expires in 10 min). Plain code: {$otp}");

        if ($this->option('sync')) {
            SendOtpPhoneNotification::dispatchSync($user->id, $otp, $user->language ?? 'ar');
            $this->info('SendOtpPhoneNotification ran synchronously.');
        } else {
            SendOtpPhoneNotification::dispatch($user->id, $otp, $user->language ?? 'ar');
            $this->warn('Job queued. Ensure a worker is running: php artisan queue:work');
        }

        $driver = strtolower((string) config('otp.phone_driver', 'log'));
        if (in_array($driver, ['log', ''], true)) {
            $this->warn('OTP_PHONE_DRIVER=log — nothing is sent to the phone. Set OTP_PHONE_DRIVER=welniz and SMS_API_KEY in .env, then php artisan config:clear');
        } else {
            $this->line("OTP_PHONE_DRIVER={$driver}");
        }

        return self::SUCCESS;
    }
}
