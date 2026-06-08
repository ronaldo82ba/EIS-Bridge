<?php

namespace App\Services;

use App\Jobs\MapInvoiceJob;
use App\Support\InvoiceBroadcaster;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Models\TransmissionLog;

class TransactionProcessor
{
    public function processSingle(array $data, $vendor)
    {
        $missingFields = [];

        if (empty($data['transaction_id'])) {
            $missingFields[] = 'transaction_id';
        }

        if (empty($data['totals']['net'])) {
            $missingFields[] = 'totals.net';
        }

        if (!empty($missingFields)) {
            $fieldList = implode(', ', $missingFields);

            return [
                'http_status' => 400,
                'status'      => 'rejected',
                'error'       => 'validation_error',
                'message'     => 'Missing required field: ' . $fieldList,
                'fields'      => $missingFields,
            ];
        }

        $deviceLockResult = $this->rejectIfDeviceLocked($data);

        if ($deviceLockResult !== null) {
            return $deviceLockResult;
        }

        $existing = Invoice::where('transaction_id', $data['transaction_id'])
            ->where('merchant_code', $data['merchant_code'] ?? '')
            ->where('branch_code', $data['branch_code'] ?? '')
            ->where('pos_device_id', $data['pos_device_id'] ?? '')
            ->first();

        if ($existing) {
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
            'transaction_id'        => $data['transaction_id'],
            'merchant_code'         => $data['merchant_code'] ?? '',
            'branch_code'           => $data['branch_code'] ?? '',
            'pos_device_id'         => $data['pos_device_id'] ?? '',
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

    public function processBatch(string $batchId, array $transactions, $vendor)
    {
        $results = [];
        $accepted = 0;
        $rejected = 0;

        foreach ($transactions as $tx) {
            $res = $this->processSingle($tx, $vendor);
            $httpStatus = $res['http_status'] ?? 201;
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

    private function rejectIfDeviceLocked(array $data): ?array
    {
        $merchantCode = (string) ($data['merchant_code'] ?? '');
        $branchCode = (string) ($data['branch_code'] ?? '');
        $posDeviceId = (string) ($data['pos_device_id'] ?? '');

        if ($merchantCode === '' || $branchCode === '' || $posDeviceId === '') {
            return null;
        }

        $merchant = Merchant::where('merchant_code', $merchantCode)->first();

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
