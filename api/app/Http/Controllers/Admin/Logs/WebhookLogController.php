<?php

namespace App\Http\Controllers\Admin\Logs;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    public function index(Request $request)
    {
        $query = WebhookDelivery::query()
            ->with(['vendor:id,name', 'invoice:id,bridge_transaction_id'])
            ->orderByDesc('created_at');

        $user = $request->user();
        if ($user->role === 'vendor_admin' && $user->vendor_id) {
            $query->where('vendor_id', $user->vendor_id);
        } elseif ($vendorId = $request->integer('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        if ($request->has('status_code')) {
            $query->where('status_code', $request->integer('status_code'));
        }

        if ($request->has('success')) {
            $query->where('success', $request->boolean('success'));
        }

        if ($from = $request->date('from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 25))
        );
    }
}
