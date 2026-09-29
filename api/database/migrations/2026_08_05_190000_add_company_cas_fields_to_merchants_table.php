<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('trade_name')->nullable()->after('name');
            $table->boolean('vat_registered')->nullable()->after('tin');
            $table->string('rdo_code', 32)->nullable()->after('vat_registered');
            $table->string('bir_ack_number')->nullable()->after('rdo_code');
            $table->date('bir_ack_date')->nullable()->after('bir_ack_number');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'trade_name',
                'vat_registered',
                'rdo_code',
                'bir_ack_number',
                'bir_ack_date',
            ]);
        });
    }
};
