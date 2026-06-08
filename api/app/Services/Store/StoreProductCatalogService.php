<?php

namespace App\Services\Store;

use App\Models\OnlineStoreProduct;
use App\Models\StoreInventoryItem;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class StoreProductCatalogService
{
    /**
     * @return Collection<int, OnlineStoreProduct|StoreInventoryItem>
     */
    public function productsForVendor(?Vendor $vendor): Collection
    {
        if ($vendor === null || $vendor->use_main_online_store_product_list) {
            return OnlineStoreProduct::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        return StoreInventoryItem::query()
            ->where('vendor_id', $vendor->id)
            ->orderBy('name')
            ->get();
    }

    public function productSource(?Vendor $vendor): string
    {
        if ($vendor === null || $vendor->use_main_online_store_product_list) {
            return 'main';
        }

        return 'inventory';
    }

    /**
     * @param  OnlineStoreProduct|StoreInventoryItem  $product
     * @return array<string, mixed>
     */
    public function transformProduct(object $product): array
    {
        return [
            'id' => $product->external_id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category,
            'brand' => $product->brand,
            'price' => (int) $product->price,
            'inStock' => (bool) $product->in_stock,
        ];
    }
}
