<?php

namespace Tests\Feature;

use App\Models\OnlineStoreProduct;
use App\Models\StoreInventoryItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Security\VendorApiKeyService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreProductsTest extends TestCase
{
    private function seedVendor(string $suffix = 'store'): Vendor
    {
        $apiKeyService = app(VendorApiKeyService::class);

        return Vendor::create([
            'name' => 'Vendor '.$suffix,
            'api_key' => $apiKeyService->hashKey('vb_test_key_'.$suffix),
            'status' => 'active',
            'use_main_online_store_product_list' => true,
        ]);
    }

    public function test_public_store_products_returns_main_catalog_by_default(): void
    {
        OnlineStoreProduct::create([
            'external_id' => 'main-1',
            'name' => 'Main Product',
            'sku' => 'MAIN-1',
            'category' => 'Hardware',
            'brand' => 'EIS Bridge',
            'price' => 1000,
            'in_stock' => true,
            'sort_order' => 1,
        ]);

        $response = $this->getJson('/v1/store/products');

        $response->assertOk()
            ->assertJsonPath('meta.source', 'main')
            ->assertJsonPath('meta.use_main_online_store_product_list', true)
            ->assertJsonPath('data.0.id', 'main-1');
    }

    public function test_public_store_products_uses_vendor_inventory_when_toggle_off(): void
    {
        $vendor = $this->seedVendor('inventory');

        OnlineStoreProduct::create([
            'external_id' => 'main-1',
            'name' => 'Main Product',
            'sku' => 'MAIN-1',
            'category' => 'Hardware',
            'brand' => 'EIS Bridge',
            'price' => 1000,
            'in_stock' => true,
            'sort_order' => 1,
        ]);

        StoreInventoryItem::create([
            'vendor_id' => $vendor->id,
            'external_id' => 'inv-1',
            'name' => 'Inventory Product',
            'sku' => 'INV-1',
            'category' => 'Services',
            'brand' => 'EIS Bridge',
            'price' => 2500,
            'in_stock' => true,
        ]);

        $vendor->update(['use_main_online_store_product_list' => false]);

        $response = $this->getJson('/v1/store/products?vendor_id='.$vendor->id);

        $response->assertOk()
            ->assertJsonPath('meta.source', 'inventory')
            ->assertJsonPath('meta.use_main_online_store_product_list', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'inv-1')
            ->assertJsonMissing(['data' => [['id' => 'main-1']]]);
    }

    public function test_admin_can_update_store_settings_toggle(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $vendor = $this->seedVendor('toggle');

        $response = $this->patchJson('/api/admin/vendors/'.$vendor->id.'/store-settings', [
            'use_main_online_store_product_list' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.use_main_online_store_product_list', false);

        $this->assertFalse($vendor->fresh()->use_main_online_store_product_list);
    }

    public function test_admin_store_inventory_preview_respects_toggle(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vendor_id' => null,
        ]));

        $vendor = $this->seedVendor('preview');

        OnlineStoreProduct::create([
            'external_id' => 'main-1',
            'name' => 'Main Product',
            'sku' => 'MAIN-1',
            'category' => 'Hardware',
            'brand' => 'EIS Bridge',
            'price' => 1000,
            'in_stock' => true,
            'sort_order' => 1,
        ]);

        StoreInventoryItem::create([
            'vendor_id' => $vendor->id,
            'external_id' => 'inv-1',
            'name' => 'Inventory Product',
            'sku' => 'INV-1',
            'category' => 'Services',
            'brand' => 'EIS Bridge',
            'price' => 2500,
            'in_stock' => true,
        ]);

        $vendor->update(['use_main_online_store_product_list' => false]);

        $response = $this->getJson('/api/admin/vendors/'.$vendor->id.'/store-inventory/preview');

        $response->assertOk()
            ->assertJsonPath('meta.source', 'inventory')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'inv-1');
    }
}
