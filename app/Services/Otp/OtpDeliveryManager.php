<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OtpDeliveryManager
{
    public function deliverToPhone(string $digitsOnly, string $message): void
    {
        $driver = strtolower((string) config('otp.phone_driver', 'log'));

        match ($driver) {
            'welniz', 'welniz_whatsapp' => $this->sendWelniz($digitsOnly, $message),
            'http', 'http_json', 'sms_http' => $this->sendHttpJson($digitsOnly, $message),
            default => $this->sendLog($digitsOnly, $message),
        };
    }

    private function sendLog(string $phone, string $message): void
    {
        Log::info('[OTP phone] (log driver — not sent)', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }

    private function sendWelniz(string $phone, string $message): void
    {
        $key = config('otp.welniz.api_key');
        if (! $key) {
            Log::error('OTP Welniz: missing WELNIZ_API_KEY or SMS_API_KEY in .env');
            throw new RuntimeException('Welniz API key not configured');
        }

        $base = config('otp.welniz.base_url');
        $instance = config('otp.welniz.instance');
        $url = $base.'/message/sendText/'.$instance;
        $timeout = (int) config('otp.welniz.timeout', 10);

        $response = Http::timeout($timeout)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $key,
            ])
            ->post($url, [
                'number' => $phone,
                'text' => $message,
                'linkPreview' => (bool) config('otp.welniz.link_preview', false),
            ]);

        if (! $response->successful()) {
            Log::error('OTP Welniz request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Welniz OTP delivery failed: HTTP '.$response->status());
        }
    }

    private function sendHttpJson(string $phone, string $message): void
    {
        $url = config('otp.http.url');
        if (! $url) {
            Log::error('OTP HTTP driver: SMS_HTTP_URL is not set');
            throw new RuntimeException('SMS HTTP URL not configured');
        }

        $bodyTemplate = config('otp.http.body', []);
        $body = $this->interpolateBody(is_array($bodyTemplate) ? $bodyTemplate : [], $phone, $message);
        $method = strtolower((string) config('otp.http.method', 'POST'));
        $timeout = (int) config('otp.http.timeout', 15);

        $req = Http::timeout($timeout)->withHeaders(array_filter((array) config('otp.http.headers', [])));

        $response = match ($method) {
            'get' => $req->get($url, $body),
            default => $req->post($url, $body),
        };

        if (! $response->successful()) {
            Log::error('OTP HTTP SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('SMS HTTP delivery failed: HTTP '.$response->status());
        }
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function interpolateBody(array $template, string $phone, string $message): array
    {
        $out = [];
        foreach ($template as $k => $v) {
            if (is_array($v)) {
                $out[$k] = $this->interpolateBody($v, $phone, $message);
            } elseif (is_string($v)) {
                $out[$k] = str_replace(
                    ['{{phone}}', '{{text}}'],
                    [$phone, $message],
                    $v
                );
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
