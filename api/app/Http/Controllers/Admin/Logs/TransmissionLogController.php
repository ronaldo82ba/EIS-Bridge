<?php

namespace App\Http\Controllers\Admin\Logs;

use App\Http\Controllers\Controller;
use App\Models\TransmissionLog;
use Illuminate\Http\Request;

class TransmissionLogController extends Controller
{
    public function index(Request $request)
    {
        $query = TransmissionLog::query()
            ->with('invoice:id,bridge_transaction_id,processing_status,eis_status')
            ->orderByDesc('timestamp');

        if ($invoiceId = $request->integer('invoice_id')) {
            $query->where('invoice_id', $invoiceId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('event', $status);
        }

        if ($from = $request->date('from')) {
            $query->where('timestamp', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('timestamp', '<=', $to->endOfDay());
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 25))
        );
    }
}
