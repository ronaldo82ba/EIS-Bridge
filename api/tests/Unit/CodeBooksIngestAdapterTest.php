<?php

namespace Tests\Unit;

use App\Models\Vendor;
use App\Services\CodeBooks\CodeBooksIngestAdapter;
use App\Services\Mapping\PosJsonValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeBooksIngestAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function minimalCodeBooksPayload(): array
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

    public function test_adapter_transforms_codebooks_export_into_valid_sale_objects(): void
    {
        $vendor = Vendor::create([
            'name' => 'Books Vendor',
            'api_key' => hash('sha256', 'books-key'),
            'status' => 'active',
        ]);

        $result = app(CodeBooksIngestAdapter::class)->ingest(
            $this->minimalCodeBooksPayload(),
            $vendor,
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertCount(1, $result['sale_objects']);

        $sale = $result['sale_objects'][0];
        app(PosJsonValidator::class)->validate($sale);

        $this->assertSame('SI-2026-000100', $sale['transaction_id']);
        $this->assertSame('SI', $sale['invoice_type']);
        $this->assertSame(CodeBooksIngestAdapter::VIRTUAL_POS_DEVICE_ID, $sale['pos_device_id']);
        $this->assertSame('00001', $sale['branch_code']);
        $this->assertSame('OTHER', $sale['payment']['method']);
        $this->assertSame(112.0, $sale['payment']['amount']);
        $this->assertSame('Buyer Co', $sale['customer']['name']);
        $this->assertSame('', $sale['customer']['address']);
        $this->assertArrayHasKey('tin', $sale['customer']);

        $this->assertSame('ACCN-PILOT-9', $result['merchant']['bir_ack_number']);
        $this->assertSame('2026-03-01', $result['merchant']['bir_ack_date']);
        $this->assertSame('dry_run', $result['results'][0]['status']);
        $this->assertSame('Demo Trade', $result['results'][0]['bir_json']['merchant']['trade_name']);
        $this->assertSame('ACCN-PILOT-9', $result['results'][0]['bir_json']['merchant']['bir_ack_number']);
    }
}
