<?php

namespace Tests\Unit;

use App\Models\Vendor;
use App\Services\Security\VendorApiKeyService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VendorApiKeyServiceTest extends TestCase
{
    private VendorApiKeyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VendorApiKeyService::class);
    }

    public function test_validate_accepts_current_api_key(): void
    {
        $plainKey = 'vb_test_current_key_1234567890';
        $vendor = Vendor::create([
            'name' => 'Current Key Vendor',
            'api_key' => $this->service->hashKey($plainKey),
        ]);

        $resolved = $this->service->validate($plainKey);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($vendor));
    }

    public function test_rotate_allows_previous_key_during_grace_period(): void
    {
        Config::set('security.api_key_grace_hours', 24);

        $vendor = Vendor::create([
            'name' => 'Rotation Vendor',
            'api_key' => '',
        ]);

        $initial = $this->service->assignInitialKey($vendor, 'vb_initial_key_abcdefghijklmnopqrst');
        $rotated = $this->service->rotate($initial['vendor']);

        $this->assertNotNull($this->service->validate($rotated['plain_key']));
        $this->assertNotNull($this->service->validate($initial['plain_key']));
        $this->assertNull($this->service->validate('vb_totally_invalid_key'));
    }

    public function test_previous_key_rejected_after_grace_period(): void
    {
        Config::set('security.api_key_grace_hours', 1);

        $vendor = Vendor::create([
            'name' => 'Expired Grace Vendor',
            'api_key' => '',
        ]);

        $initial = $this->service->assignInitialKey($vendor, 'vb_grace_old_key_abcdefghijklmnopqrstuv');
        $rotated = $this->service->rotate($initial['vendor']);

        $vendor->update(['api_key_rotated_at' => now()->subHours(2)]);

        $this->assertNotNull($this->service->validate($rotated['plain_key']));
        $this->assertNull($this->service->validate($initial['plain_key']));
    }
}
