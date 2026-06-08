<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\MerchantPtt;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMerchantDetailTest extends TestCase
{
    private function seedMerchantWithRelations(string $suffix = '001'): array
    {
        $vendor = Vendor::create([
            'name' => 'Vendor '.$suffix,
            'api_key' => hash('sha256', 'merchant-detail-'.$suffix),
            'status' => 'active',
        ]);

        $merchant = Merchant::create([
            'vendor_id' => $vendor->id,
            'merchant_code' => 'MRC-'.$suffix,
            'name' => 'Merchant '.$suffix,
            'tin' => '123-456-789-000',
            'address' => '123 Commerce St',
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'merchant_id' => $merchant->id,
            'branch_code' => 'BR-'.$suffix,
            'name' => 'Main Branch',
            'status' => 'active',
        ]);

        $device = Device::create([
            'branch_id' => $branch->id,
            'pos_device_id' => 'POS-'.$suffix,
            'name' => 'Terminal 1',
            'status' => 'active',
        ]);

        $certificate = MerchantCertificate::create([
            'merchant_id' => $merchant->id,
            'filename' => 'cert.pfx',
            'file_path' => Crypt::encryptString('certificates/'.$merchant->id.'/cert.pfx'),
            'password_encrypted' => Crypt::encryptString('secret'),
            'expires_at' => now()->addYear(),
        ]);

        $ptt = MerchantPtt::create([
            'merchant_id' => $merchant->id,
            'ptt_number' => 'PTT-'.$suffix,
            'valid_from' => Carbon::today()->subMonth(),
            'valid_to' => Carbon::today()->addYear(),
            'status' => 'active',
        ]);

        return [$vendor, $merchant, $branch, $device, $certificate, $ptt];
    }

    public function test_admin_merchant_show_includes_branches_devices_certificate_ptt_and_stats(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        [, $merchant, $branch, $device, $certificate] = $this->seedMerchantWithRelations('SHOW');

        Invoice::create([
            'bridge_transaction_id' => 'BRG-MRC-1',
            'transaction_id' => 'POS-MRC-1',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => $branch->branch_code,
            'pos_device_id' => $device->pos_device_id,
            'raw_pos_json' => ['transaction_id' => 'POS-MRC-1'],
            'processing_status' => 'completed',
            'eis_status' => 'acknowledged',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Invoice::create([
            'bridge_transaction_id' => 'BRG-MRC-2',
            'transaction_id' => 'POS-MRC-2',
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => $branch->branch_code,
            'pos_device_id' => $device->pos_device_id,
            'raw_pos_json' => ['transaction_id' => 'POS-MRC-2'],
            'processing_status' => 'retry_failed',
            'eis_status' => 'rejected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/admin/merchants/{$merchant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $merchant->id)
            ->assertJsonPath('data.name', $merchant->name)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'merchant_code',
                    'vendor',
                    'branches' => [
                        '*' => [
                            'id',
                            'branch_code',
                            'devices' => [
                                '*' => ['id', 'pos_device_id'],
                            ],
                        ],
                    ],
                    'certificate' => ['id', 'expires_at'],
                    'ptt' => ['id', 'ptt_number'],
                    'stats' => [
                        'today_total',
                        'today_ack',
                        'today_rejected',
                        'failures',
                    ],
                ],
            ]);

        $payload = $response->json('data');

        $this->assertCount(1, $payload['branches']);
        $this->assertSame($branch->id, $payload['branches'][0]['id']);
        $this->assertCount(1, $payload['branches'][0]['devices']);
        $this->assertSame($device->id, $payload['branches'][0]['devices'][0]['id']);
        $this->assertSame($certificate->id, $payload['certificate']['id']);
        $this->assertSame('PTT-SHOW', $payload['ptt']['ptt_number']);
        $this->assertSame(2, $payload['stats']['today_total']);
        $this->assertSame(1, $payload['stats']['today_ack']);
        $this->assertSame(1, $payload['stats']['today_rejected']);
        $this->assertSame(1, $payload['stats']['failures']);
    }

    public function test_vendor_admin_can_view_scoped_merchant(): void
    {
        [$vendor, $merchant] = $this->seedMerchantWithRelations('OWN');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $vendor->id,
        ]));

        $this->getJson("/api/admin/merchants/{$merchant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $merchant->id);
    }

    public function test_vendor_admin_cannot_view_other_vendor_merchant(): void
    {
        [$ownVendor, $ownMerchant] = $this->seedMerchantWithRelations('OWN-SCOPE');
        [, $otherMerchant] = $this->seedMerchantWithRelations('OTHER-SCOPE');

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_VENDOR_ADMIN,
            'vendor_id' => $ownVendor->id,
        ]));

        $this->getJson("/api/admin/merchants/{$ownMerchant->id}")
            ->assertOk();

        $this->getJson("/api/admin/merchants/{$otherMerchant->id}")
            ->assertForbidden();
    }
}
