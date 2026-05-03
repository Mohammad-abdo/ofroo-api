<?php

namespace Tests\Unit;

use App\Services\Otp\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_strips_non_digits(): void
    {
        $this->assertSame('201234567890', PhoneNormalizer::digitsOnly('+20 123 456 7890'));
        $this->assertSame('201122334455', PhoneNormalizer::digitsOnly('+20 11 22 33 44 55'));
    }

    public function test_egypt_local_to_international_for_sms_gateway(): void
    {
        $this->assertSame('201120916853', PhoneNormalizer::digitsForSmsGateway('01120916853'));
        $this->assertSame('201120916853', PhoneNormalizer::digitsForSmsGateway('+20 11 20916853'));
    }
}
