<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeBooksIngestTokenTest extends TestCase
{
    use RefreshDatabase;

    private function minimalPayload(): array
    {
        return [
            'meta' => [
                'source' => 'codebooks',
                'exported_at' => '2026-08-05T10:00:00+08:00',
            ],
            'company' => [
                'name' => 'WebShoppe Demo Co',
                'trade_name' => 'Demo Trade',
                'tin' => '987-654-321-000',
                'address' => 'Makati City',
                'vat_registered' => true,
                'rdo_code' => '050',
                'branch_code' => '00001',
                'bir_ack_number' => 'ACCN-PILOT-9',
                'bir_ack_date' => '2026-03-01',
            ],
            'documents' => [
                [
                    'document_type' => 'SI',
                    'number' => 'SI-2026-000100',
                    'date' => '2026-08-01',
                    'customer' => [
                        'name' => 'Buyer Co',
                        'tin' => '111-222-333-000',
                        'address' => null,
                    ],
                    'lines' => [
                        [
                            'line_no' => 1,
                            'sku' => 'SKU-A',
                            'description' => 'Widget',
                            'qty' => 1,
                            'unit_price' => 112.0,
                            'vat_rate' => 12,
                        ],
                    ],
                    'totals' => [
                        'gross_amount' => 112.0,
                        'net_amount' => 100.0,
                        'vat_amount' => 12.0,
                        'vatable_sales' => 100.0,
                    ],
                ],
            ],
        ];
    }

    public function test_token_ingest_dry_run_returns_sale_objects_and_bir_json(): void
    {
        $token = 'test-codebooks-ingest-token';
        config([
            'codebooks.ingest_token' => $token,
            'codebooks.ingest_vendor_id' => null,
        ]);

        $vendor = Vendor::create([
            'name' => 'Books Vendor',
            'api_key' => hash('sha256', 'books-key'),
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'X-Bridge-Ingest-Token' => $token,
        ])->postJson('/api/admin/codebooks/ingest/service', [
            'vendor_id' => $vendor->id,
            'dry_run' => true,
            'payload' => $this->minimalPayload(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.results.0.status', 'dry_run')
            ->assertJsonPath('data.sale_objects.0.transaction_id', 'SI-2026-000100')
            ->assertJsonPath('data.sale_objects.0.pos_device_id', 'CODEBOOKS-VIRTUAL')
            ->assertJsonPath('data.results.0.bir_json.merchant.bir_ack_number', 'ACCN-PILOT-9');

        $this->assertIsArray($response->json('data.results.0.bir_json'));
        $this->assertIsArray($response->json('data.sale_objects.0'));
    }

    public function test_token_ingest_rejects_missing_token(): void
    {
        config([
            'codebooks.ingest_token' => 'expected-token',
            'codebooks.ingest_vendor_id' => null,
        ]);

        $vendor = Vendor::create([
            'name' => 'Books Vendor',
            'api_key' => hash('sha256', 'books-key-2'),
            'status' => 'active',
        ]);

        $this->postJson('/api/admin/codebooks/ingest/service', [
            'vendor_id' => $vendor->id,
            'payload' => $this->minimalPayload(),
        ])->assertUnauthorized();
    }

    public function test_token_ingest_rejects_vendor_mismatch_with_config(): void
    {
        $token = 'test-codebooks-ingest-token';

        $vendorA = Vendor::create([
            'name' => 'Vendor A',
            'api_key' => hash('sha256', 'a-key'),
            'status' => 'active',
        ]);
        $vendorB = Vendor::create([
            'name' => 'Vendor B',
            'api_key' => hash('sha256', 'b-key'),
            'status' => 'active',
        ]);

        config([
            'codebooks.ingest_token' => $token,
            'codebooks.ingest_vendor_id' => $vendorA->id,
        ]);

        $this->withHeaders([
            'X-Bridge-Ingest-Token' => $token,
        ])->postJson('/api/admin/codebooks/ingest/service', [
            'vendor_id' => $vendorB->id,
            'dry_run' => true,
            'payload' => $this->minimalPayload(),
        ])->assertForbidden();
    }
}
