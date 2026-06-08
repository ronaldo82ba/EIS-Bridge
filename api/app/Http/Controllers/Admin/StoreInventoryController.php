<?php

namespace App\Http\Controllers\Admin;

use App\Models\StoreInventoryItem;
use App\Models\Vendor;
use App\Services\Store\StoreProductCatalogService;
use Illuminate\Http\Request;

class StoreInventoryController extends AdminController
{
    public function __construct(
        private readonly StoreProductCatalogService $catalog,
    ) {}

    public function index(Vendor $vendor)
    {
        $this->authorize('update', $vendor);

        $items = StoreInventoryItem::query()
            ->where('vendor_id', $vendor->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $items->map(fn (StoreInventoryItem $item) => $this->transformItem($item)),
            'meta' => [
                'use_main_online_store_product_list' => (bool) $vendor->use_main_online_store_product_list,
            ],
        ]);
    }

    public function store(Request $request, Vendor $vendor)
    {
        $this->authorize('update', $vendor);

        $data = $this->validatedItem($request);

        $item = StoreInventoryItem::create([
            ...$data,
            'vendor_id' => $vendor->id,
        ]);

        return response()->json(['data' => $this->transformItem($item)], 201);
    }

    public function update(Request $request, Vendor $vendor, StoreInventoryItem $inventoryItem)
    {
        $this->authorize('update', $vendor);
        $this->ensureVendorOwnsItem($vendor, $inventoryItem);

        $inventoryItem->update($this->validatedItem($request, $inventoryItem));

        return response()->json(['data' => $this->transformItem($inventoryItem->fresh())]);
    }

    public function destroy(Vendor $vendor, StoreInventoryItem $inventoryItem)
    {
        $this->authorize('update', $vendor);
        $this->ensureVendorOwnsItem($vendor, $inventoryItem);

        $inventoryItem->delete();

        return response()->json(null, 204);
    }

    public function updateSettings(Request $request, Vendor $vendor)
    {
        $this->authorize('update', $vendor);

        $data = $request->validate([
            'use_main_online_store_product_list' => ['required', 'boolean'],
        ]);

        $vendor->update($data);

        return response()->json([
            'data' => [
                'use_main_online_store_product_list' => (bool) $vendor->use_main_online_store_product_list,
            ],
        ]);
    }

    public function preview(Vendor $vendor)
    {
        $this->authorize('view', $vendor);

        $products = $this->catalog->productsForVendor($vendor);

        return response()->json([
            'data' => $products
                ->map(fn ($product) => $this->catalog->transformProduct($product))
                ->values(),
            'meta' => [
                'use_main_online_store_product_list' => (bool) $vendor->use_main_online_store_product_list,
                'source' => $this->catalog->productSource($vendor),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request, ?StoreInventoryItem $existing = null): array
    {
        $externalIdRule = ['required', 'string', 'max:64'];

        if ($existing) {
            $externalIdRule[] = 'unique:store_inventory_items,external_id,'.$existing->id.',id,vendor_id,'.$existing->vendor_id;
        } else {
            $externalIdRule[] = 'unique:store_inventory_items,external_id,NULL,id,vendor_id,'.$request->route('vendor')->id;
        }

        $data = $request->validate([
            'external_id' => $externalIdRule,
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64'],
            'category' => ['required', 'string', 'max:128'],
            'brand' => ['required', 'string', 'max:128'],
            'price' => ['required', 'integer', 'min:0'],
            'in_stock' => ['sometimes', 'boolean'],
        ]);

        $data['in_stock'] = $data['in_stock'] ?? true;

        return $data;
    }

    private function ensureVendorOwnsItem(Vendor $vendor, StoreInventoryItem $item): void
    {
        abort_unless($item->vendor_id === $vendor->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformItem(StoreInventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'external_id' => $item->external_id,
            'name' => $item->name,
            'sku' => $item->sku,
            'category' => $item->category,
            'brand' => $item->brand,
            'price' => (int) $item->price,
            'in_stock' => (bool) $item->in_stock,
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }
}
