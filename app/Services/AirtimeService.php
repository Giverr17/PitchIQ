<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AirtimeService
{
    /**
     * Send airtime to a phone number.
     * Returns ['success' => bool, 'reference' => string, 'message' => string].
     *
     * Provider: VTU.ng (https://vtu.ng/wp-json), API v2.
     * Simulated unless services.airtime.live (AIRTIME_LIVE) is true.
     */
    public function send(string $phone, int $amount): array
    {
        // Toggle this when you have real VTU credentials
        $useRealProvider = config('services.airtime.live', false);

        return $useRealProvider
            ? $this->sendReal($phone, $amount)
            : $this->sendSimulated($phone, $amount);
    }

    private function sendSimulated(string $phone, int $amount): array
    {
        // Pretend the provider accepted it. Basic phone sanity check.
        $valid = preg_match('/^0\d{10}$/', $phone); // Nigerian 11-digit format

        if (!$valid) {
            return [
                'success' => false,
                'reference' => null,
                'message' => 'Invalid phone number format.',
            ];
        }

        return [
            'success' => true,
            'reference' => 'SIM-' . strtoupper(Str::random(10)),
            'message' => "Simulated airtime of ₦{$amount} to {$phone}.",
        ];
    }

    private function sendReal(string $phone, int $amount): array
    {
        // 1. Detect the network — needed for service_id
        $network = $this->detectNetwork($phone);
        if (!$network) {
            return ['success' => false, 'reference' => null, 'message' => 'Could not detect network for this phone number.'];
        }

        // 2. Get an auth token
        $token = $this->getToken();
        if (!$token) {
            return ['success' => false, 'reference' => null, 'message' => 'Could not authenticate with airtime provider.'];
        }

        // 3. Build a unique request_id (idempotency key, max 50 chars)
        $requestId = 'pitchiq_' . uniqid();

        // 4. POST the airtime order
        try {
            $base = config('services.airtime.base_url');

            $response = Http::withToken($token)
                ->timeout(30)
                ->post("{$base}/api/v2/airtime", [
                    'request_id' => $requestId,
                    'phone' => $phone,
                    'service_id' => $network,
                    'amount' => $amount,
                ]);

            $data = $response->json('data') ?? [];
            $status = $data['status'] ?? null;
            $code = $response->json('code');

            // 5a. Success states
            $acceptedStatuses = ['completed-api', 'processing-api', 'queued-api', 'initiated-api'];
            if ($response->successful() && $code === 'success' && in_array($status, $acceptedStatuses)) {
                return [
                    'success' => true,
                    'reference' => (string) ($data['order_id'] ?? $requestId),
                    'message' => $response->json('message') ?? 'Airtime sent.',
                ];
            }

            // 5b. Anything else is a failure
            return [
                'success' => false,
                'reference' => $data['order_id'] ?? null,
                'message' => $response->json('message') ?? 'Airtime request was not accepted.',
            ];

        } catch (\Throwable $e) {
            Log::error('VTU.ng airtime send failed', ['error' => $e->getMessage(), 'phone' => $phone]);
            return ['success' => false, 'reference' => null, 'message' => 'Airtime request error: ' . $e->getMessage()];
        }
    }

    private function getToken(): ?string
    {
        // 1. If we already have a cached token, reuse it — no API call needed
        $cached = Cache::get('vtu_token');
        if ($cached) {
            return $cached;
        }

        // 2. No cached token — log in to get a fresh one
        $base = config('services.airtime.base_url');

        $response = Http::timeout(30)->post("{$base}/jwt-auth/v1/token", [
            'username' => config('services.airtime.username'),
            'password' => config('services.airtime.password'),
        ]);

        // 3. If login failed, log it and return null (caller handles the failure)
        if (!$response->successful() || empty($response->json('token'))) {
            Log::warning('VTU.ng auth failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        // 4. Got a token — cache it for 6 days (expiry is 7), then return it
        $token = $response->json('token');
        Cache::put('vtu_token', $token, now()->addDays(6));

        return $token;
    }

    private function detectNetwork(string $phone): ?string
    {
        // 1. Normalize to 0XXXXXXXXXX format
        $phone = preg_replace('/\D/', '', $phone);          // strip non-digits
        if (str_starts_with($phone, '234')) {
            $phone = '0' . substr($phone, 3);                // +234803... → 0803...
        }
        if (strlen($phone) === 10) {
            $phone = '0' . $phone;                           // 803... → 0803...
        }

        // Must now be 11 digits starting with 0
        if (strlen($phone) !== 11 || !str_starts_with($phone, '0')) {
            return null;
        }

        // 2. Take the 4-digit prefix
        $prefix = substr($phone, 0, 4);

        // 3. Look it up in the network table
        $networks = [
            'mtn' => ['0803', '0806', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916', '0703', '0706'],
            'airtel' => ['0802', '0808', '0812', '0701', '0708', '0901', '0902', '0904', '0907', '0912'],
            'glo' => ['0805', '0807', '0811', '0815', '0905', '0915'],
            '9mobile' => ['0809', '0817', '0818', '0908', '0909'],
        ];

        foreach ($networks as $network => $prefixes) {
            if (in_array($prefix, $prefixes)) {
                return $network;
            }
        }

        return null; // unknown prefix
    }
}