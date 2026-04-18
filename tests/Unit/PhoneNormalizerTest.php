<?php

namespace Tests\Unit;

use App\Services\Otp\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_strips_non_digits(): void
    {
        $this->assertSame('201234567890', PhoneNormalizer::digitsOnly('+20 123 456 7890'));
        $this->assertSame('96512345678', PhoneNormalizer::digitsOnly('+965 1234 5678'));
    }

    public function test_egypt_local_to_international_for_sms_gateway(): void
    {
        $this->assertSame('201120916853', PhoneNormalizer::digitsForSmsGateway('01120916853'));
        $this->assertSame('201120916853', PhoneNormalizer::digitsForSmsGateway('+20 11 20916853'));
    }
}
