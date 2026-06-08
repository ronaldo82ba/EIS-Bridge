<?php

namespace Database\Seeders;

use App\Models\OnlineStoreProduct;
use App\Models\StoreInventoryItem;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class OnlineStoreProductSeeder extends Seeder
{
    public function run(): void
    {
        $mainProducts = [
            ['external_id' => 'p1', 'name' => 'Thermal Receipt Printer', 'sku' => 'TRP-200', 'category' => 'Hardware', 'brand' => 'PrintPro', 'price' => 8499, 'in_stock' => true, 'sort_order' => 1],
            ['external_id' => 'p2', 'name' => 'Barcode Scanner USB', 'sku' => 'BCS-110', 'category' => 'Hardware', 'brand' => 'ScanTech', 'price' => 3299, 'in_stock' => true, 'sort_order' => 2],
            ['external_id' => 'p3', 'name' => 'Cash Drawer 16"', 'sku' => 'CDR-016', 'category' => 'Hardware', 'brand' => 'PrintPro', 'price' => 4599, 'in_stock' => false, 'sort_order' => 3],
            ['external_id' => 'p4', 'name' => 'POS Terminal Bundle', 'sku' => 'POS-BND-01', 'category' => 'Bundles', 'brand' => 'EIS Bridge', 'price' => 24999, 'in_stock' => true, 'sort_order' => 4],
            ['external_id' => 'p5', 'name' => 'Merchant License — Starter', 'sku' => 'LIC-M-ST', 'category' => 'Licenses', 'brand' => 'EIS Bridge', 'price' => 4999, 'in_stock' => true, 'sort_order' => 5],
            ['external_id' => 'p6', 'name' => 'Merchant License — Enterprise', 'sku' => 'LIC-M-EN', 'category' => 'Licenses', 'brand' => 'EIS Bridge', 'price' => 19999, 'in_stock' => true, 'sort_order' => 6],
            ['external_id' => 'p7', 'name' => 'Vendor White-Label License', 'sku' => 'LIC-V-WL', 'category' => 'Licenses', 'brand' => 'EIS Bridge', 'price' => 99999, 'in_stock' => true, 'sort_order' => 7],
            ['external_id' => 'p8', 'name' => 'EIS Integration Toolkit', 'sku' => 'KIT-EIS-01', 'category' => 'Software', 'brand' => 'EIS Bridge', 'price' => 1499, 'in_stock' => true, 'sort_order' => 8],
            ['external_id' => 'p9', 'name' => 'JSON Schema Validator CLI', 'sku' => 'SW-VAL-01', 'category' => 'Software', 'brand' => 'DevTools', 'price' => 999, 'in_stock' => true, 'sort_order' => 9],
            ['external_id' => 'p10', 'name' => 'Postman Collection Pro', 'sku' => 'SW-PM-01', 'category' => 'Software', 'brand' => 'DevTools', 'price' => 499, 'in_stock' => false, 'sort_order' => 10],
            ['external_id' => 'p11', 'name' => 'Tablet Stand Adjustable', 'sku' => 'ACC-TS-01', 'category' => 'Accessories', 'brand' => 'RetailGear', 'price' => 1299, 'in_stock' => true, 'sort_order' => 11],
            ['external_id' => 'p12', 'name' => 'USB-C Hub 7-Port', 'sku' => 'ACC-HUB-07', 'category' => 'Accessories', 'brand' => 'RetailGear', 'price' => 1899, 'in_stock' => true, 'sort_order' => 12],
            ['external_id' => 'p13', 'name' => 'Label Printer 58mm', 'sku' => 'LBP-058', 'category' => 'Hardware', 'brand' => 'PrintPro', 'price' => 5799, 'in_stock' => true, 'sort_order' => 13],
            ['external_id' => 'p14', 'name' => 'SaaS Compliance Plan', 'sku' => 'LIC-SAAS-M', 'category' => 'Licenses', 'brand' => 'EIS Bridge', 'price' => 999, 'in_stock' => true, 'sort_order' => 14],
            ['external_id' => 'p15', 'name' => 'Onboarding Support Pack', 'sku' => 'SVC-ONB-01', 'category' => 'Services', 'brand' => 'EIS Bridge', 'price' => 7500, 'in_stock' => true, 'sort_order' => 15],
            ['external_id' => 'p16', 'name' => 'Certificate Management Add-on', 'sku' => 'SW-CERT-01', 'category' => 'Software', 'brand' => 'EIS Bridge', 'price' => 2499, 'in_stock' => true, 'sort_order' => 16],
        ];

        foreach ($mainProducts as $product) {
            OnlineStoreProduct::updateOrCreate(
                ['external_id' => $product['external_id']],
                $product,
            );
        }

        $vendor = Vendor::query()->first();

        if ($vendor === null) {
            return;
        }

        $inventoryItems = [
            ['external_id' => 'inv1', 'name' => 'Store POS Kit', 'sku' => 'ST-POS-01', 'category' => 'Bundles', 'brand' => 'EIS Bridge', 'price' => 18999, 'in_stock' => true],
            ['external_id' => 'inv2', 'name' => 'Store Receipt Paper (50 rolls)', 'sku' => 'ST-RP-50', 'category' => 'Accessories', 'brand' => 'PrintPro', 'price' => 899, 'in_stock' => true],
            ['external_id' => 'inv3', 'name' => 'Local Support Package', 'sku' => 'ST-SUP-01', 'category' => 'Services', 'brand' => 'EIS Bridge', 'price' => 5500, 'in_stock' => true],
        ];

        foreach ($inventoryItems as $item) {
            StoreInventoryItem::updateOrCreate(
                ['vendor_id' => $vendor->id, 'external_id' => $item['external_id']],
                $item,
            );
        }

        $vendor->update(['use_main_online_store_product_list' => false]);
    }
}
