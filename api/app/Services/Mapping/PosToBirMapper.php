<?php

namespace App\Services\Mapping;

use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\MerchantPtt;
use Carbon\Carbon;

class PosToBirMapper
{
    /**
     * Map POS sale JSON to BIR EIS invoice structure.
     *
     * Skeleton aliases: invoiceNumber→transaction_id, invoiceDateTime→transaction_datetime,
     * merchantCode→merchant.code, branchCode→branch.code, posId→device.pos_device_id,
     * items→line_items, totals→totals, payment→payment.
     */
    public function __construct(
        private PosJsonValidator $validator,
        private ItemMapper $itemMapper,
        private TotalsMapper $totalsMapper,
        private CustomerMapper $customerMapper,
    ) {}

    public function map(array|string|Invoice $input): array
    {
        if ($input instanceof Invoice) {
            $pos = $input->raw_pos_json ?? [];
            $bridgeTransactionId = $input->bridge_transaction_id;
        } elseif (is_string($input)) {
            $pos = json_decode($input, true) ?? [];
            $bridgeTransactionId = null;
        } else {
            $pos = $input;
            $bridgeTransactionId = null;
        }

        $this->validator->validate($pos);

        $merchant = Merchant::where('merchant_code', $pos['merchant_code'])->first();
        $branch = $merchant
            ? Branch::where('merchant_id', $merchant->id)->where('branch_code', $pos['branch_code'])->first()
            : null;
        $device = $branch
            ? Device::where('branch_id', $branch->id)->where('pos_device_id', $pos['pos_device_id'])->first()
            : null;
        $ptt = $merchant?->ptt;

        $lineItems = $this->itemMapper->map($pos['items']);
        $totals = $this->totalsMapper->map($pos['totals'], $lineItems);
        $customer = $this->customerMapper->map($pos['customer'] ?? null);

        $transactionDatetime = Carbon::parse($pos['transaction_datetime'])->toIso8601String();

        $bir = [
            'document_type' => strtoupper((string) $pos['invoice_type']),
            'transaction_id' => (string) $pos['transaction_id'],
            'transaction_datetime' => $transactionDatetime,
            'currency' => strtoupper((string) ($pos['currency'] ?? 'PHP')),
            'merchant' => [
                'code' => (string) $pos['merchant_code'],
                'name' => $merchant?->name,
                'tin' => $merchant?->tin,
                'address' => $merchant?->address,
            ],
            'branch' => [
                'code' => (string) $pos['branch_code'],
                'name' => $branch?->name,
                'address' => $branch?->address,
            ],
            'device' => [
                'pos_device_id' => (string) $pos['pos_device_id'],
                'name' => $device?->name,
            ],
            'ptt' => $ptt ? [
                'ptt_number' => $ptt->ptt_number,
                'valid_from' => $ptt->valid_from?->toDateString(),
                'valid_to' => $ptt->valid_to?->toDateString(),
                'status' => $ptt->status,
            ] : null,
            'line_items' => $lineItems,
            'totals' => $totals,
            'payment' => [
                'method' => strtoupper((string) $pos['payment']['method']),
                'amount' => round((float) $pos['payment']['amount'], 2),
                'details' => $pos['payment']['details'] ?? null,
            ],
            'references' => $this->mapReferences($pos['references'] ?? []),
            'metadata' => $pos['metadata'] ?? null,
            'eis_fields' => [
                'bridge_transaction_id' => $bridgeTransactionId,
                'submission_version' => '1.0',
                'source' => 'EIS_BRIDGE',
            ],
        ];

        if ($customer) {
            $bir['customer'] = $customer;
        }

        return $bir;
    }

    private function mapReferences(array $references): ?array
    {
        if (empty($references)) {
            return null;
        }

        return [
            'original_transaction_id' => $references['original_transaction_id'] ?? null,
            'return_or_void' => (bool) ($references['return_or_void'] ?? false),
            'return_reason' => $references['return_reason'] ?? null,
        ];
    }
}
