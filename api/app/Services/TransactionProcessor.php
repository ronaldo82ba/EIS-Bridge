<?php

namespace App\Services;

use App\Jobs\MapInvoiceJob;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;
use App\Models\Vendor;
use App\Support\InvoiceBroadcaster;
use App\Services\Billing\LicenseEnforcement;
use App\Http\Requests\StoreTransactionRequest;
use Illuminate\Support\Arr;

class TransactionProcessor
{
    public function __construct(
        private readonly LicenseEnforcement $licenseEnforcement,
    ) {}

    public function processSingle(array $data, Vendor $vendor)
    {
        $transactionId = (string) ($data['transaction_id'] ?? '');
        $merchantCode = (string) ($data['merchant_code'] ?? '');
        $branchCode = (string) ($data['branch_code'] ?? '');
        $posDeviceId = (string) ($data['pos_device_id'] ?? '');
        $netTotal = $data['totals']['net'] ?? null;

        if ($transactionId === '' || $merchantCode === '' || $branchCode === '' || $posDeviceId === '' || ! is_numeric($netTotal)) {
            return [
                'http_status' => 422,
                'status' => 'rejected',
                'error' => 'validation_error',
                'message' => 'The transaction payload is invalid.',
                'details' => ['transaction' => ['Required identity and totals fields are missing or invalid.']],
            ];
        }

        if (! $this->hasMappingRequiredFields($data)) {
            return [
                'http_status' => 422,
                'status' => 'rejected',
                'error' => 'validation_error',
                'message' => 'The transaction payload is invalid.',
                'details' => [
                    'transaction' => [
                        'Required mapping fields must be present: transaction_datetime, invoice_type, items, payment.',
                    ],
                ],
            ];
        }

        $semanticValidationErrors = $this->validatePayloadSemantics($data);
        if ($semanticValidationErrors !== []) {
            return [
                'http_status' => 422,
                'status' => 'rejected',
                'error' => 'validation_error',
                'message' => 'The transaction payload is invalid.',
                'details' => $semanticValidationErrors,
            ];
        }

        $merchant = Merchant::where('merchant_code', $merchantCode)
            ->where('vendor_id', $vendor->id)
            ->first();

        if (! $merchant) {
            return [
                'http_status' => 403,
                'status'      => 'rejected',
                'error'       => 'merchant_not_owned',
                'message'     => 'Merchant is not registered for this vendor.',
            ];
        }

        if (! $this->licenseEnforcement->canMerchantOperate($merchant)) {
            return [
                'http_status' => 403,
                'status'      => 'rejected',
                'error'       => 'license_suspended',
                'message'     => 'Merchant license is not active.',
            ];
        }

        $deviceLockResult = $this->rejectIfDeviceLocked($data, $vendor);

        if ($deviceLockResult !== null) {
            return $deviceLockResult;
        }

        $existing = Invoice::where('transaction_id', $transactionId)
            ->where('merchant_code', $merchantCode)
            ->where('branch_code', $branchCode)
            ->where('pos_device_id', $posDeviceId)
            ->first();

        if ($existing) {
            if ($this->buildTransactionFingerprint($existing->raw_pos_json ?? []) !== $this->buildTransactionFingerprint($data)) {
                return [
                    'http_status' => 409,
                    'status' => 'rejected',
                    'error' => 'transaction_conflict',
                    'transaction_id' => $existing->transaction_id,
                    'bridge_transaction_id' => $existing->bridge_transaction_id,
                    'message' => 'Transaction ID already exists with a different payload.',
                ];
            }

            return [
                'http_status'           => 200,
                'status'                => 'duplicate',
                'transaction_id'        => $existing->transaction_id,
                'bridge_transaction_id' => $existing->bridge_transaction_id,
                'message'               => 'Transaction already processed.',
            ];
        }

        $bridgeId = Invoice::generateBridgeTransactionId();

        $invoice = Invoice::create([
            'bridge_transaction_id' => $bridgeId,
            'transaction_id'        => $transactionId,
            'merchant_code'         => $merchantCode,
            'branch_code'           => $branchCode,
            'pos_device_id'         => $posDeviceId,
            'raw_pos_json'          => $data,
            'processing_status'     => 'queued',
        ]);

        TransmissionLog::create([
            'invoice_id' => $invoice->id,
            'event'      => 'queued',
            'timestamp'  => now(),
            'metadata'   => null,
        ]);

        InvoiceBroadcaster::created($invoice);

        MapInvoiceJob::dispatch($invoice->id)->onQueue('mapping');

        return [
            'http_status'           => 201,
            'status'                => 'accepted',
            'transaction_id'        => $invoice->transaction_id,
            'bridge_transaction_id' => $invoice->bridge_transaction_id,
            'merchant_code'         => $invoice->merchant_code,
            'branch_code'           => $invoice->branch_code,
            'pos_device_id'         => $invoice->pos_device_id,
            'processing_status'     => $invoice->processing_status,
            'message'               => 'Transaction accepted for EIS processing.',
        ];
    }

    public function processBatch(string $batchId, array $transactions, Vendor $vendor)
    {
        $results = [];
        $accepted = 0;
        $rejected = 0;

        foreach ($transactions as $tx) {
            $res = $this->processSingle($tx, $vendor);
            unset($res['http_status']);
            $results[] = $res;

            if (($res['status'] ?? '') === 'accepted') {
                $accepted++;
            } else {
                $rejected++;
            }
        }

        return [
            'status'   => 'accepted',
            'batch_id' => $batchId,
            'summary'  => [
                'total'    => count($transactions),
                'accepted' => $accepted,
                'rejected' => $rejected,
            ],
            'results'  => $results,
        ];
    }

    private function hasMappingRequiredFields(array $data): bool
    {
        return isset($data['transaction_datetime'], $data['invoice_type'], $data['items'], $data['payment'])
            && is_array($data['items'])
            && count($data['items']) > 0
            && is_array($data['payment']);
    }

    private function validatePayloadSemantics(array $data): array
    {
        $details = [];

        if (! StoreTransactionRequest::isStrictIso8601((string) ($data['transaction_datetime'] ?? ''))) {
            $details['transaction_datetime'][] = 'The transaction_datetime must be a valid ISO 8601 datetime.';
        }

        $invoiceType = strtolower((string) ($data['invoice_type'] ?? ''));
        $items = (array) ($data['items'] ?? []);
        foreach ($items as $index => $item) {
            $qty = (float) ($item['qty'] ?? 0);
            if ($invoiceType !== 'refund' && $qty <= 0) {
                $details["items.{$index}.qty"][] = 'The qty must be greater than 0 unless invoice_type is REFUND.';
            }

            $unitPrice = (float) ($item['unit_price'] ?? 0);
            if ($unitPrice <= 0) {
                $details["items.{$index}.unit_price"][] = 'The unit_price must be greater than 0.';
            }
        }

        $sanitizationViolations = StoreTransactionRequest::collectSanitizationViolations($data, '');
        foreach ($sanitizationViolations as $path => $messages) {
            $key = ltrim((string) $path, '.');
            $details[$key] = array_merge($details[$key] ?? [], $messages);
        }

        return $details;
    }

    private function buildTransactionFingerprint(array $data): string
    {
        $identity = [
            'merchant_code' => (string) ($data['merchant_code'] ?? ''),
            'branch_code' => (string) ($data['branch_code'] ?? ''),
            'pos_device_id' => (string) ($data['pos_device_id'] ?? ''),
            'transaction_id' => (string) ($data['transaction_id'] ?? ''),
            'amount' => round((float) Arr::get($data, 'totals.net', 0), 2),
            'items' => $this->normalizeItems((array) ($data['items'] ?? [])),
        ];

        return hash('sha256', json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $normalized[] = [
                'sku' => (string) ($item['sku'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'qty' => (float) ($item['qty'] ?? 0),
                'unit_price' => round((float) ($item['unit_price'] ?? 0), 2),
            ];
        }

        usort($normalized, static function (array $a, array $b): int {
            return strcmp(
                implode('|', [$a['sku'], $a['description'], $a['qty'], $a['unit_price']]),
                implode('|', [$b['sku'], $b['description'], $b['qty'], $b['unit_price']])
            );
        });

        return $normalized;
    }

    private function rejectIfDeviceLocked(array $data, Vendor $vendor): ?array
    {
        $merchantCode = (string) ($data['merchant_code'] ?? '');
        $branchCode = (string) ($data['branch_code'] ?? '');
        $posDeviceId = (string) ($data['pos_device_id'] ?? '');

        if ($merchantCode === '' || $branchCode === '' || $posDeviceId === '') {
            return null;
        }

        $merchant = Merchant::where('merchant_code', $merchantCode)
            ->where('vendor_id', $vendor->id)
            ->first();

        if (! $merchant) {
            return null;
        }

        $branch = Branch::where('merchant_id', $merchant->id)
            ->where('branch_code', $branchCode)
            ->first();

        if (! $branch) {
            return null;
        }

        $device = Device::where('branch_id', $branch->id)
            ->where('pos_device_id', $posDeviceId)
            ->first();

        if ($device && $device->status === 'locked') {
            return [
                'http_status' => 403,
                'status' => 'rejected',
                'error' => 'device_locked',
                'message' => 'This POS device is locked and cannot send transactions.',
            ];
        }

        return null;
    }
}
