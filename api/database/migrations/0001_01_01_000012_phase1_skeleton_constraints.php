<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unique(['merchant_id', 'branch_code']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->unique(['branch_id', 'pos_device_id']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'pos_device_id']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['merchant_id', 'branch_code']);
        });
    }
};
