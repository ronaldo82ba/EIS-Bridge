<?php

namespace App\Http\Controllers\Admin;

use App\Models\CertificateAlert;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateAlertController extends AdminController
{
    public function index(Request $request): JsonResponse
    {
        $query = CertificateAlert::query()
            ->with([
                'certificate:id,merchant_id,filename,expires_at',
                'certificate.merchant:id,name,merchant_code,vendor_id',
            ])
            ->orderByDesc('created_at');

        if ($vendorId = AdminScope::vendorId($this->adminUser())) {
            $query->whereHas('certificate.merchant', fn ($q) => $q->where('vendor_id', $vendorId));
        }

        if ($level = $request->string('level')->toString()) {
            $query->where('level', $level);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }
}
