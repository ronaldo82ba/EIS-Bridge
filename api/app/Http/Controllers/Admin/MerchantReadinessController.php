<?php

/**
 * Deviations from user skeleton:
 * - Uses MerchantReadinessService (not inline exists checks) for signing_test + mapping_test.
 * - Extends AdminController with authorize('view', $merchant).
 * - Named show action (skeleton used __invoke); route registered explicitly in admin.php.
 */

namespace App\Http\Controllers\Admin;

use App\Models\Merchant;
use App\Services\Onboarding\MerchantReadinessService;
use Illuminate\Http\JsonResponse;

class MerchantReadinessController extends AdminController
{
    public function show(Merchant $merchant): JsonResponse
    {
        $this->authorize('view', $merchant);

        return response()->json([
            'data' => app(MerchantReadinessService::class)->assess($merchant),
        ]);
    }
}
