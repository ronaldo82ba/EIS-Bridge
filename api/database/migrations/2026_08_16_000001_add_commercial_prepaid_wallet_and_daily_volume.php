<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->decimal('prepaid_wallet_balance', 12, 2)->default(0)->after('status');
        });

        Schema::create('prepaid_wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type'); // credit | debit
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reason');
            $table->foreignId('billing_invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'created_at']);
            $table->index('entry_type');
        });

        Schema::create('merchant_daily_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedInteger('invoice_count')->default(0);
            $table->timestamps();

            $table->unique(['merchant_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_daily_volumes');
        Schema::dropIfExists('prepaid_wallet_ledgers');

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('prepaid_wallet_balance');
        });
    }
};
