<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Otp\OtpDeliveryManager;
use App\Services\Otp\PhoneNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOtpPhoneNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $userId,
        public string $otpPlain,
        public string $language = 'ar'
    ) {}

    public function handle(OtpDeliveryManager $delivery): void
    {
        $user = User::query()->find($this->userId);
        if (! $user || ! $user->phone) {
            Log::warning('SendOtpPhoneNotification: user or phone missing', ['user_id' => $this->userId]);

            return;
        }

        $digits = PhoneNormalizer::digitsForSmsGateway($user->phone);
        if (strlen($digits) < 8) {
            Log::warning('SendOtpPhoneNotification: phone too short after normalize', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        $message = $this->language === 'en'
            ? "Your OFROO code: {$this->otpPlain}. Valid 10 minutes. Do not share."
            : "رمز التحقق OFROO: {$this->otpPlain} صالح 10 دقائق. لا تشاركه مع أحد.";

        $delivery->deliverToPhone($digits, $message);
    }
}
