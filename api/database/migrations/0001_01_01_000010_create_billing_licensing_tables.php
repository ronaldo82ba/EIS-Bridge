<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('billing_model');
            $table->string('unit')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['billing_model', 'is_active']);
        });

        Schema::create('vendor_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('merchant_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('license_plan_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('billable_type');
            $table->unsignedBigInteger('billable_id');
            $table->foreignId('license_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status')->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id']);
            $table->index(['status', 'due_at']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('license_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('merchant_licenses');
        Schema::dropIfExists('vendor_licenses');
        Schema::dropIfExists('license_plans');
    }
};
