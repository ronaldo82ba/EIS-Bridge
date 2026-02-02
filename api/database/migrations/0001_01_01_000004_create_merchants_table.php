<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('merchant_code');
            $table->string('name');
            $table->timestamps();

            $table->unique(['vendor_id', 'merchant_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
