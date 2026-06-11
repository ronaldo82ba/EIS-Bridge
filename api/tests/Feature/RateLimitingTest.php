<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Services\Security\VendorApiKeyService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    public function test_vendor_api_returns_429_after_limit(): void
    {
        Config::set('security.vendor_api_rate_limit', 2);

        $plainKey = 'vb_rate_limit_test_key_abcdefghijklmnop';
        $service = app(VendorApiKeyService::class);

        Vendor::create([
            'name' => 'Rate Limited Vendor',
            'api_key' => $service->hashKey($plainKey),
        ]);

        $headers = ['Authorization' => 'Bearer '.$plainKey];

        $this->getJson('/v1/transactions', $headers)->assertOk();
        $this->getJson('/v1/transactions', $headers)->assertOk();

        $response = $this->getJson('/v1/transactions', $headers);

        $response->assertStatus(429)
            ->assertJson([
                'error' => 'too_many_requests',
                'message' => 'Rate limit exceeded.',
            ]);
    }

    public function test_vendor_transactions_route_has_independent_rate_limit(): void
    {
        Config::set('security.vendor_api_rate_limit', 100);
        Config::set('security.vendor_transaction_rate_limit', 1);

        $plainKey = 'vb_tx_rate_limit_test_key_abcdefghijklmnop';
        $service = app(VendorApiKeyService::class);

        Vendor::create([
            'name' => 'Transaction Rate Limited Vendor',
            'api_key' => $service->hashKey($plainKey),
        ]);

        $headers = ['Authorization' => 'Bearer '.$plainKey];
        $payload = [
            'transaction' => [
                'transaction_id' => 'TX-RL-1',
                'merchant_code' => 'NOT-OWNED',
                'branch_code' => 'BR001',
                'pos_device_id' => 'POS01',
                'totals' => ['net' => 100],
            ],
        ];

        $this->postJson('/v1/transactions', $payload, $headers)->assertStatus(403);

        $this->postJson('/v1/transactions', $payload, $headers)
            ->assertStatus(429)
            ->assertJson([
                'error' => 'too_many_requests',
                'message' => 'Transaction rate limit exceeded.',
            ]);
    }

    public function test_login_returns_429_after_limit(): void
    {
        Config::set('security.login_rate_limit', 2);

        $payload = ['email' => 'nobody@example.com', 'password' => 'wrong-password'];

        $this->postJson('/api/admin/login', $payload)->assertStatus(422);
        $this->postJson('/api/admin/login', $payload)->assertStatus(422);

        $this->postJson('/api/admin/login', $payload)
            ->assertStatus(429)
            ->assertJson([
                'error' => 'too_many_requests',
            ]);
    }
}
