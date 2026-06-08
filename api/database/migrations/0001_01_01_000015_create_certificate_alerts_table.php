<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')
                ->constrained('merchant_certificates')
                ->cascadeOnDelete();
            $table->string('level', 32);
            $table->boolean('notified_admin')->default(false);
            $table->boolean('notified_vendor')->default(false);
            $table->timestamps();

            $table->unique(['certificate_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_alerts');
    }
};
