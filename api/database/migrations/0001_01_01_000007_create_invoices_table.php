<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_transaction_id')->unique();
            $table->string('transaction_id');
            $table->string('merchant_code');
            $table->string('branch_code');
            $table->string('pos_device_id');
            $table->json('raw_pos_json');
            $table->json('bir_json')->nullable();
            $table->json('signed_json')->nullable();
            $table->string('processing_status')->default('queued');
            $table->string('eis_status')->nullable();
            $table->string('eis_reference_no')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
