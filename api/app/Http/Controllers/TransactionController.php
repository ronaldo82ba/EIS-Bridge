<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use Illuminate\Http\Request;
use App\Services\TransactionProcessor;
use App\Models\Invoice;

class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request, TransactionProcessor $processor)
    {
        $data = $request->validated('transaction');

        $result = $processor->processSingle($data, $request->attributes->get('vendor'));

        $status = $result['http_status'] ?? 201;
        unset($result['http_status']);

        return response()->json($result, $status);
    }

    public function batch(BatchTransactionRequest $request, TransactionProcessor $processor)
    {
        $batchId = (string) $request->validated('batch_id');
        $transactions = $request->validated('transactions');

        $result = $processor->processBatch($batchId, $transactions, $request->attributes->get('vendor'));

        return response()->json($result, 201);
    }

    public function show(Request $request, $bridgeTransactionId)
    {
        $vendor = $request->attributes->get('vendor');
        $merchantCodes = $vendor->merchants()->pluck('merchant_code');

        $invoice = Invoice::where('bridge_transaction_id', $bridgeTransactionId)
            ->whereIn('merchant_code', $merchantCodes)
            ->first();

        if (!$invoice) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Transaction not found.',
            ], 404);
        }

        $response = [
            'bridge_transaction_id' => $invoice->bridge_transaction_id,
            'transaction_id'        => $invoice->transaction_id,
            'merchant_code'         => $invoice->merchant_code,
            'branch_code'           => $invoice->branch_code,
            'pos_device_id'         => $invoice->pos_device_id,
            'processing_status'     => $invoice->processing_status,
            'eis_status'            => $invoice->eis_status,
            'eis_reference_no'      => $invoice->eis_reference_no,
            'last_update'           => $invoice->updated_at?->toIso8601String(),
        ];

        if ($invoice->transmissionLogs->isNotEmpty()) {
            $response['logs'] = $invoice->transmissionLogs->map(fn ($log) => [
                'timestamp' => $log->timestamp?->toIso8601String(),
                'event'     => $log->event,
            ])->values()->all();
        }

        return response()->json($response);
    }

    public function index(Request $request)
    {
        $vendor = $request->attributes->get('vendor');
        $merchantCodes = $vendor->merchants()->pluck('merchant_code');

        $query = Invoice::query()->whereIn('merchant_code', $merchantCodes);

        if ($mc = $request->query('merchant_code')) {
            $query->where('merchant_code', $mc);
        }

        if ($bc = $request->query('branch_code')) {
            $query->where('branch_code', $bc);
        }

        if ($status = $request->query('status')) {
            $query->where('processing_status', $status);
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        $invoices = $query->paginate($request->query('page_size', 50));

        return response()->json($invoices);
    }
}
