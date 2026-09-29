<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL utf8mb4: four default string(255) columns exceed the 3072-byte index limit.
        // Prefix lengths cover real POS/vendor codes while staying under the limit.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'CREATE INDEX invoices_dedup_index ON invoices '
                .'(transaction_id(100), merchant_code(64), branch_code(64), pos_device_id(64))'
            );

            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(
                ['transaction_id', 'merchant_code', 'branch_code', 'pos_device_id'],
                'invoices_dedup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_dedup_index');
        });
    }
};
