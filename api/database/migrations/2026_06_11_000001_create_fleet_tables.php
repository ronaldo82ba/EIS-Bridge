<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->unique();
            $table->string('device_serial')->nullable();
            $table->string('device_model')->nullable();
            $table->string('token_hash');
            $table->text('token_encrypted');
            $table->string('callback_base_url')->nullable();
            $table->string('status')->default('online');
            $table->json('last_status')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('last_seen_at');
        });

        Schema::create('fleet_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('command');
            $table->json('payload')->nullable();
            $table->json('targets');
            $table->string('auth_source');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('completed_targets')->default(0);
            $table->unsignedInteger('failed_targets')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('fleet_task_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fleet_task_id')->constrained('fleet_tasks')->cascadeOnDelete();
            $table->foreignUuid('fleet_agent_id')->constrained('fleet_agents')->cascadeOnDelete();
            $table->string('agent_id');
            $table->string('status')->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['fleet_task_id', 'status']);
            $table->index(['fleet_agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_task_results');
        Schema::dropIfExists('fleet_tasks');
        Schema::dropIfExists('fleet_agents');
    }
};
