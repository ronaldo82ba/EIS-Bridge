<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_store_products', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('name');
            $table->string('sku');
            $table->string('category');
            $table->string('brand');
            $table->unsignedInteger('price');
            $table->boolean('in_stock')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('sku');
            $table->string('category');
            $table->string('brand');
            $table->unsignedInteger('price');
            $table->boolean('in_stock')->default(true);
            $table->timestamps();

            $table->unique(['vendor_id', 'external_id']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('use_main_online_store_product_list')
                ->default(true)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('use_main_online_store_product_list');
        });

        Schema::dropIfExists('store_inventory_items');
        Schema::dropIfExists('online_store_products');
    }
};
