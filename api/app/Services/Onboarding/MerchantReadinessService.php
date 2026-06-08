<?php

namespace App\Services\Onboarding;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Merchant;
use App\Models\MerchantCertificate;
use App\Models\MerchantPtt;
use App\Services\Mapping\PosToBirMapper;
use App\Services\Signing\CertificateLoader;
use App\Services\Signing\JsonSigner;
use Carbon\Carbon;
use Throwable;

class MerchantReadinessService
{
    public function __construct(
        private readonly CertificateLoader $certificateLoader,
        private readonly JsonSigner $jsonSigner,
        private readonly PosToBirMapper $posToBirMapper,
    ) {}

    /**
     * @return array{merchant: string, ready: bool, checks: array<string, bool>}
     */
    public function assess(Merchant $merchant): array
    {
        $merchant->loadMissing(['branches.devices', 'ptt', 'certificates']);

        $checks = [
            'merchant_info' => $this->checkMerchantInfo($merchant),
            'branches' => $merchant->branches->isNotEmpty(),
            'devices' => $this->checkDevices($merchant),
            'certificate' => $this->checkCertificate($merchant),
            'ptt' => $this->checkPtt($merchant),
            'signing_test' => $this->runSigningTest($merchant),
            'mapping_test' => $this->runMappingTest($merchant),
        ];

        return [
            'merchant' => $merchant->name,
            'ready' => ! in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }

    private function checkMerchantInfo(Merchant $merchant): bool
    {
        return filled($merchant->name)
            && filled($merchant->tin)
            && filled($merchant->address)
            && filled($merchant->vendor_id)
            && in_array($merchant->status, ['active', 'inactive'], true);
    }

    private function checkDevices(Merchant $merchant): bool
    {
        return $merchant->branches->sum(fn (Branch $branch) => $branch->devices->count()) >= 1;
    }

    private function checkCertificate(Merchant $merchant): bool
    {
        $certificate = $merchant->certificates
            ->sortByDesc('id')
            ->first();

        if (! $certificate instanceof MerchantCertificate) {
            return false;
        }

        if ($certificate->expires_at && $certificate->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    private function checkPtt(Merchant $merchant): bool
    {
        $ptt = $merchant->ptt;

        if (! $ptt instanceof MerchantPtt) {
            return false;
        }

        if ($ptt->status !== 'active') {
            return false;
        }

        $today = Carbon::today();

        if ($ptt->valid_from && $ptt->valid_from->gt($today)) {
            return false;
        }

        if ($ptt->valid_to && $ptt->valid_to->lt($today)) {
            return false;
        }

        return filled($ptt->ptt_number);
    }

    private function runSigningTest(Merchant $merchant): bool
    {
        try {
            $cert = $this->certificateLoader->loadForMerchant($merchant->id);
            $payload = $this->sampleBirPayload($merchant);
            $signed = $this->jsonSigner->sign($payload, $cert['path'], $cert['password']);

            return filled($signed['signature'] ?? null);
        } catch (Throwable) {
            return false;
        }
    }

    private function runMappingTest(Merchant $merchant): bool
    {
        try {
            $pos = $this->samplePosPayload($merchant);
            $bir = $this->posToBirMapper->map($pos);

            return filled($bir['transaction_id'] ?? null)
                && ($bir['merchant']['code'] ?? null) === $merchant->merchant_code;
        } catch (Throwable) {
            return false;
        }
    }

    private function sampleBirPayload(Merchant $merchant): array
    {
        $branch = $merchant->branches->first();
        $device = $branch?->devices->first();

        return [
            'document_type' => 'OR',
            'transaction_id' => 'READINESS-'.now()->format('YmdHis'),
            'transaction_datetime' => now()->toIso8601String(),
            'currency' => 'PHP',
            'merchant' => [
                'code' => $merchant->merchant_code,
                'name' => $merchant->name,
                'tin' => $merchant->tin,
            ],
            'branch' => [
                'code' => $branch?->branch_code ?? 'TEST',
            ],
            'device' => [
                'pos_device_id' => $device?->pos_device_id ?? 'TEST',
            ],
            'line_items' => [[
                'line_no' => 1,
                'sku' => 'TEST',
                'description' => 'Readiness test item',
                'quantity' => 1,
                'unit_price' => 100,
                'gross_amount' => 100,
            ]],
            'totals' => [
                'gross_amount' => 100,
                'discount_amount' => 0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0,
                'zero_rated_sales' => 0,
                'service_charge' => 0,
                'net_amount' => 100,
            ],
            'payment' => ['method' => 'CASH', 'amount' => 100],
            'eis_fields' => [
                'submission_version' => '1.0',
                'source' => 'EIS_BRIDGE',
            ],
        ];
    }

    private function samplePosPayload(Merchant $merchant): array
    {
        $branch = $merchant->branches->first();
        $device = $branch?->devices->first();

        return [
            'transaction_id' => 'READINESS-MAP-'.now()->format('YmdHis'),
            'transaction_datetime' => now()->toIso8601String(),
            'merchant_code' => $merchant->merchant_code,
            'branch_code' => $branch?->branch_code ?? 'BR001',
            'pos_device_id' => $device?->pos_device_id ?? 'POS01',
            'invoice_type' => 'OR',
            'currency' => 'PHP',
            'items' => [[
                'line_no' => 1,
                'sku' => 'SKU001',
                'description' => 'Readiness test item',
                'qty' => 1,
                'unit_price' => 100.0,
                'discount' => 0.0,
                'vat_rate' => 12.0,
            ]],
            'totals' => [
                'gross' => 100.0,
                'discount' => 0.0,
                'vatable_sales' => 89.29,
                'vat_amount' => 10.71,
                'vat_exempt_sales' => 0.0,
                'zero_rated_sales' => 0.0,
                'service_charge' => 0.0,
                'net' => 100.0,
            ],
            'payment' => [
                'method' => 'CASH',
                'amount' => 100.0,
            ],
        ];
    }
}
