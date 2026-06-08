<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('super_admin')->after('password');
            $table->foreignId('vendor_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('status')->default('active')->after('webhook_secret');
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->string('tin')->nullable()->after('name');
            $table->text('address')->nullable()->after('tin');
            $table->string('status')->default('active')->after('address');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->text('address')->nullable()->after('name');
            $table->string('status')->default('active')->after('address');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('status')->default('active')->after('name');
        });

        Schema::create('merchant_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('file_path');
            $table->text('password_encrypted');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('merchant_ptt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('ptt_number');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique('merchant_id');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('request_url');
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('merchant_ptt');
        Schema::dropIfExists('merchant_certificates');

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['address', 'status']);
        });

        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn(['tin', 'address', 'status']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_id');
            $table->dropColumn('role');
        });
    }
};
