<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Services\Store\StoreProductCatalogService;
use Illuminate\Http\Request;

class StoreProductController extends Controller
{
    public function __construct(
        private readonly StoreProductCatalogService $catalog,
    ) {}

    public function index(Request $request)
    {
        $vendor = null;

        if ($vendorId = $request->query('vendor_id')) {
            $vendor = Vendor::query()->find($vendorId);
        }

        $products = $this->catalog->productsForVendor($vendor);

        return response()->json([
            'data' => $products
                ->map(fn ($product) => $this->catalog->transformProduct($product))
                ->values(),
            'meta' => [
                'vendor_id' => $vendor?->id,
                'use_main_online_store_product_list' => $vendor?->use_main_online_store_product_list ?? true,
                'source' => $this->catalog->productSource($vendor),
            ],
        ]);
    }
}
