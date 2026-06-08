<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20);
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('channel')->default('app');
            $table->timestamp('logged_at');
            $table->timestamps();

            $table->index(['level', 'logged_at']);
            $table->index('logged_at');
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('severity');
            $table->string('title');
            $table->text('message');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'entity_type', 'entity_id', 'resolved_at']);
            $table->index(['severity', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('system_logs');
    }
};
