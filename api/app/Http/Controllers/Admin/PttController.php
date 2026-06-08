<?php

/**
 * Deviations from user skeleton:
 * - Model is MerchantPtt (skeleton uses Ptt); one PTT per merchant via updateOrCreate.
 * - No MerchantPtt policy; authorization via MerchantPolicy update on parent merchant.
 * - upsert route retained for PUT /merchants/{merchant}/ptt onboarding alias.
 * - Audit logging on create/upsert; responses wrapped in { data: ... }.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Merchant;
use App\Models\MerchantPtt;
use App\Services\Audit\AuditLogger;
use App\Support\AdminScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PttController extends AdminController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_id' => ['required', 'exists:merchants,id'],
            'ptt_number'  => ['required', 'string', 'max:100'],
            'valid_from'  => ['required', 'date'],
            'valid_to'    => ['required', 'date', 'after_or_equal:valid_from'],
            'status'      => ['sometimes', 'in:active,inactive,expired'],
        ]);

        $merchant = Merchant::findOrFail($data['merchant_id']);
        $this->authorize('update', $merchant);

        if (! AdminScope::belongsToVendor($this->adminUser(), $merchant->vendor_id)) {
            abort(403);
        }

        $ptt = MerchantPtt::updateOrCreate(
            ['merchant_id' => $merchant->id],
            [
                'ptt_number' => $data['ptt_number'],
                'valid_from' => $data['valid_from'],
                'valid_to'   => $data['valid_to'],
                'status'     => $data['status'] ?? 'active',
            ],
        );

        AuditLogger::log($this->adminUser(), 'created_ptt', 'merchant', $merchant->id, $ptt->toArray());

        return response()->json(['data' => $ptt], 201);
    }

    public function upsert(Request $request, Merchant $merchant): JsonResponse
    {
        $this->authorize('update', $merchant);

        $data = $request->validate([
            'ptt_number' => ['required', 'string', 'max:100'],
            'valid_from' => ['nullable', 'date'],
            'valid_to'   => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status'     => ['sometimes', 'in:active,inactive,expired'],
        ]);

        $ptt = MerchantPtt::updateOrCreate(
            ['merchant_id' => $merchant->id],
            array_merge($data, ['status' => $data['status'] ?? 'active']),
        );

        AuditLogger::log($this->adminUser(), 'upserted_ptt', 'merchant', $merchant->id, $ptt->toArray());

        return response()->json(['data' => $ptt]);
    }
}
