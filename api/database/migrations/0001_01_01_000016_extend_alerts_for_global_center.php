<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('category', 32)->nullable()->after('type');
            $table->foreignId('merchant_id')->nullable()->after('metadata')->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->after('merchant_id')->constrained()->nullOnDelete();
            $table->foreignId('certificate_id')->nullable()->after('invoice_id')
                ->constrained('merchant_certificates')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('certificate_id')->constrained()->nullOnDelete();
            $table->json('details')->nullable()->after('vendor_id');

            $table->index(['category', 'resolved_at']);
            $table->index(['merchant_id', 'resolved_at']);
            $table->index(['vendor_id', 'resolved_at']);
            $table->index(['invoice_id', 'category', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['category', 'resolved_at']);
            $table->dropIndex(['merchant_id', 'resolved_at']);
            $table->dropIndex(['vendor_id', 'resolved_at']);
            $table->dropIndex(['invoice_id', 'category', 'resolved_at']);

            $table->dropConstrainedForeignId('vendor_id');
            $table->dropConstrainedForeignId('certificate_id');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn(['category', 'details']);
        });
    }
};
