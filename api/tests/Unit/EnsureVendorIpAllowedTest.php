<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureVendorIpAllowed;
use App\Models\Vendor;
use App\Models\VendorIpWhitelist;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Tests\TestCase;

class EnsureVendorIpAllowedTest extends TestCase
{
    public function test_cidr_matching_allows_client_ip(): void
    {
        $this->assertTrue(IpUtils::checkIp('192.168.1.50', '192.168.1.0/24'));
        $this->assertFalse(IpUtils::checkIp('10.0.0.5', '192.168.1.0/24'));
        $this->assertTrue(IpUtils::checkIp('203.0.113.10', '203.0.113.10'));
    }

    public function test_empty_whitelist_allows_request(): void
    {
        $vendor = Vendor::create([
            'name' => 'Open Vendor',
            'api_key' => hash_hmac('sha256', 'test-key', (string) config('app.key')),
        ]);

        $request = Request::create('/v1/transactions', 'POST', server: ['REMOTE_ADDR' => '203.0.113.1']);
        $request->attributes->set('vendor', $vendor);

        $middleware = new EnsureVendorIpAllowed;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_whitelisted_ip_allows_request(): void
    {
        $vendor = Vendor::create([
            'name' => 'Restricted Vendor',
            'api_key' => hash_hmac('sha256', 'test-key', (string) config('app.key')),
        ]);

        VendorIpWhitelist::create([
            'vendor_id' => $vendor->id,
            'ip_address' => '192.168.10.0/24',
            'is_active' => true,
        ]);

        $request = Request::create('/v1/transactions', 'POST', server: ['REMOTE_ADDR' => '192.168.10.42']);
        $request->attributes->set('vendor', $vendor);

        $middleware = new EnsureVendorIpAllowed;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_non_whitelisted_ip_is_forbidden(): void
    {
        $vendor = Vendor::create([
            'name' => 'Blocked Vendor',
            'api_key' => hash_hmac('sha256', 'test-key', (string) config('app.key')),
        ]);

        VendorIpWhitelist::create([
            'vendor_id' => $vendor->id,
            'ip_address' => '10.10.10.0/24',
            'is_active' => true,
        ]);

        $request = Request::create('/v1/transactions', 'POST', server: ['REMOTE_ADDR' => '172.16.0.1']);
        $request->attributes->set('vendor', $vendor);

        $middleware = new EnsureVendorIpAllowed;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('forbidden', $response->getData(true)['error']);
    }
}
