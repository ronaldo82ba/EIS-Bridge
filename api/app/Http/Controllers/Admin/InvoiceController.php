<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\Bulk\ForceResignJob;
use App\Jobs\Bulk\ForceRetransmitJob;
use App\Jobs\Bulk\RetryMappingJob;
use App\Jobs\Bulk\RetrySigningJob;
use App\Jobs\Bulk\RetryTransmissionJob;
use App\Jobs\ProcessInvoiceJob;
use App\Models\Invoice;
use App\Models\Merchant;
use App\Services\Analytics\InvoiceAnalyticsService;
use App\Services\Audit\AuditLogger;
use App\Support\AdminScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InvoiceController extends AdminController
{
    public function analytics(Request $request, InvoiceAnalyticsService $analytics)
    {
        $this->authorize('viewAny', Invoice::class);

        $data = $analytics->getAnalytics(
            user: $this->adminUser(),
            range: $request->query('range', '7d'),
            vendorId: $request->integer('vendor_id') ?: null,
            merchantCode: $request->query('merchant_code'),
        );

        return response()->json(['data' => $data]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $query = AdminScope::scopeInvoices(Invoice::query(), $this->adminUser())
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('processing_status', $status);
        }

        if ($eisStatus = $request->query('eis_status')) {
            $query->where('eis_status', $eisStatus);
        }

        if ($merchantCode = $request->query('merchant_code')) {
            $query->where('merchant_code', $merchantCode);
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $query = AdminScope::scopeInvoices(
            Invoice::query()->with('merchant:id,merchant_code,name'),
            $this->adminUser(),
        )->orderByDesc('created_at');

        if ($search = $request->query('q')) {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('bridge_transaction_id', 'like', $term)
                    ->orWhere('transaction_id', 'like', $term)
                    ->orWhere('merchant_code', 'like', $term)
                    ->orWhereHas('merchant', fn (Builder $m) => $m->where('name', 'like', $term));
            });
        }

        if ($merchantId = $request->query('merchant_id')) {
            $merchantCode = Merchant::query()
                ->where('id', $merchantId)
                ->value('merchant_code');

            if ($merchantCode) {
                $query->where('merchant_code', $merchantCode);
            }
        } elseif ($merchantCode = $request->query('merchant_code')) {
            $query->where('merchant_code', $merchantCode);
        }

        if ($status = $request->query('status')) {
            $query->where(function (Builder $q) use ($status) {
                $this->applySearchStatusFilter($q, $status);
            });
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($stuckIn = $request->query('stuck_in')) {
            $stuckStatuses = [
                'mapping' => 'mapping',
                'signing' => 'signing',
                'transmission' => 'transmitting',
            ];

            if (isset($stuckStatuses[$stuckIn])) {
                $query->where('processing_status', $stuckStatuses[$stuckIn]);
            }
        }

        if ($request->boolean('has_errors')) {
            $query->where(function (Builder $q) {
                $q->whereIn('processing_status', ['failed', 'retry_failed', 'transmission_failed'])
                    ->orWhere('eis_status', 'rejected');
            });
        }

        if ($request->boolean('webhook_failed')) {
            $query->whereHas('webhookDeliveries', fn (Builder $q) => $q->where('success', false));
        }

        $perPage = min(max($request->integer('per_page', 25), 1), 100);

        return response()->json($query->paginate($perPage));
    }

    private function applySearchStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            'pending' => $query->where(function (Builder $q) {
                $q->whereIn('processing_status', ['queued', 'pending'])
                    ->orWhereNull('eis_status')
                    ->orWhere('eis_status', 'pending');
            }),
            'mapped' => $query->where('processing_status', 'mapped'),
            'signed' => $query->where('processing_status', 'signed'),
            'transmitted' => $query->where('processing_status', 'transmitting'),
            'sent' => $query->where('processing_status', 'sent'),
            'acknowledged' => $query->where('eis_status', 'acknowledged'),
            'rejected' => $query->where(function (Builder $q) {
                $q->where('eis_status', 'rejected')
                    ->orWhereIn('processing_status', ['failed', 'rejected']);
            }),
            'retry_failed' => $query->where('processing_status', 'retry_failed'),
            'transmission_failed' => $query->where('processing_status', 'transmission_failed'),
            default => $query->where('processing_status', $status)
                ->orWhere('eis_status', $status),
        };
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'transmissionLogs' => fn ($query) => $query->orderBy('timestamp'),
        ]);

        $invoice->setRelation('logs', $invoice->transmissionLogs);

        return response()->json(['data' => $invoice]);
    }

    public function retry(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->update(['processing_status' => 'queued']);
        ProcessInvoiceJob::dispatch($invoice->id);

        return response()->json([
            'message' => 'Invoice queued for retry.',
            'data' => $invoice->fresh(),
        ]);
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:retry_mapping,retry_signing,retry_transmission,force_resign,force_retransmit,resolve',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:invoices,id',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        $invoices = AdminScope::scopeInvoices(
            Invoice::query()->whereIn('id', $ids),
            $this->adminUser(),
        )->get();

        if ($invoices->count() !== count(array_unique($ids))) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Unauthorized access to one or more invoices.',
            ], 403);
        }

        foreach ($invoices as $invoice) {
            $this->authorize('view', $invoice);
            $this->authorize('update', $invoice);
        }

        $queued = 0;

        foreach ($invoices as $invoice) {
            match ($action) {
                'retry_mapping' => RetryMappingJob::dispatch($invoice->id),
                'retry_signing' => RetrySigningJob::dispatch($invoice->id),
                'retry_transmission' => RetryTransmissionJob::dispatch($invoice->id),
                'force_resign' => ForceResignJob::dispatch($invoice->id),
                'force_retransmit' => ForceRetransmitJob::dispatch($invoice->id),
                'resolve' => $invoice->update(['processing_status' => 'resolved']),
            };
            $queued++;
        }

        AuditLogger::log(
            $this->adminUser(),
            'invoice.bulk_'.$action,
            'invoice',
            null,
            ['invoice_ids' => $ids, 'queued' => $queued],
        );

        return response()->json(['data' => ['queued' => $queued]]);
    }
}
