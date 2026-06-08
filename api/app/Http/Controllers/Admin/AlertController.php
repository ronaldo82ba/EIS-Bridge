<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Support\AlertPresenter;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = Alert::query()
            ->with([
                'merchant:id,name',
                'vendor:id,name',
                'invoice:id,bridge_transaction_id',
                'certificate:id,merchant_id,filename',
            ])
            ->orderByDesc('created_at');

        $type = $request->string('type')->toString();
        if ($type !== '' && $type !== 'all') {
            $query->where(function ($q) use ($type) {
                $q->where('category', $type)
                    ->orWhere(function ($legacy) use ($type) {
                        $legacy->whereNull('category')
                            ->whereIn('type', $this->legacyTypesForCategory($type));
                    });
            });
        }

        $status = $request->string('status')->toString();
        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        } elseif ($request->boolean('active_only', false)) {
            $query->whereNull('resolved_at');
        }

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->integer('merchant_id'));
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->integer('vendor_id'));
        }

        if ($severity = $request->string('severity')->toString()) {
            $query->where('severity', $severity);
        }

        $paginated = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            ...$paginated->toArray(),
            'data' => collect($paginated->items())->map(fn (Alert $alert) => $this->transformAlert($alert))->values(),
        ]);
    }

    public function summary()
    {
        $active = Alert::query()->whereNull('resolved_at');

        return response()->json([
            'total_active' => (clone $active)->count(),
            'by_severity' => [
                'critical' => (clone $active)->where('severity', Alert::SEVERITY_CRITICAL)->count(),
                'warning' => (clone $active)->where('severity', Alert::SEVERITY_WARNING)->count(),
                'info' => (clone $active)->where('severity', Alert::SEVERITY_INFO)->count(),
            ],
            'by_type' => [
                'processing' => $this->countActiveByCategory(Alert::CATEGORY_PROCESSING),
                'eis' => $this->countActiveByCategory(Alert::CATEGORY_EIS),
                'certificate' => $this->countActiveByCategory(Alert::CATEGORY_CERTIFICATE),
                'webhook' => $this->countActiveByCategory(Alert::CATEGORY_WEBHOOK),
                'system' => $this->countActiveByCategory(Alert::CATEGORY_SYSTEM),
            ],
            'unacknowledged' => (clone $active)->whereNull('acknowledged_at')->count(),
        ]);
    }

    public function acknowledge(Request $request, Alert $alert)
    {
        if ($alert->resolved_at) {
            return response()->json([
                'error' => 'already_resolved',
                'message' => 'Cannot acknowledge a resolved alert.',
            ], 422);
        }

        $alert->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'alert' => $this->transformAlert($alert->fresh('acknowledgedBy:id,name')),
        ]);
    }

    public function resolve(Alert $alert)
    {
        $alert->update([
            'resolved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'alert' => $this->transformAlert($alert->fresh()),
        ]);
    }

    private function transformAlert(Alert $alert): array
    {
        return AlertPresenter::transform($alert);
    }

    private function countActiveByCategory(string $category): int
    {
        return Alert::query()
            ->whereNull('resolved_at')
            ->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhere(function ($legacy) use ($category) {
                        $legacy->whereNull('category')
                            ->whereIn('type', $this->legacyTypesForCategory($category));
                    });
            })
            ->count();
    }

    /**
     * @return list<string>
     */
    private function legacyTypesForCategory(string $category): array
    {
        return match ($category) {
            Alert::CATEGORY_CERTIFICATE => [
                Alert::TYPE_CERTIFICATE_EXPIRING,
                Alert::TYPE_PTT_EXPIRING,
            ],
            Alert::CATEGORY_SYSTEM => [
                Alert::TYPE_HIGH_ERROR_RATE,
                Alert::TYPE_QUEUE_BACKLOG,
                Alert::TYPE_LICENSE_EXPIRING,
            ],
            default => [],
        };
    }
}
