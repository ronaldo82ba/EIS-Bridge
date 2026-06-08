<?php

namespace Tests\Unit;

use App\Services\Observability\QueueMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_depth_map_returns_waiting_and_processing_per_queue(): void
    {
        $now = now()->timestamp;

        DB::table('jobs')->insert([
            [
                'queue' => 'mapping',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ],
            [
                'queue' => 'mapping',
                'payload' => '{}',
                'attempts' => 1,
                'reserved_at' => $now,
                'available_at' => $now,
                'created_at' => $now,
            ],
            [
                'queue' => 'webhooks',
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $now,
                'created_at' => $now,
            ],
        ]);

        $map = app(QueueMonitor::class)->queueDepthMap();

        $this->assertSame(1, $map['mapping']['waiting']);
        $this->assertSame(1, $map['mapping']['processing']);
        $this->assertSame(1, $map['webhooks']['waiting']);
        $this->assertSame(0, $map['webhooks']['processing']);
        $this->assertArrayHasKey('transmission', $map);
    }

    public function test_worker_heartbeats_cover_monitored_queues(): void
    {
        $heartbeats = app(QueueMonitor::class)->workerHeartbeats();

        $this->assertNotEmpty($heartbeats);
        $this->assertContains('webhooks', collect($heartbeats)->pluck('name')->all());

        foreach ($heartbeats as $heartbeat) {
            $this->assertArrayHasKey('name', $heartbeat);
            $this->assertArrayHasKey('alive', $heartbeat);
            $this->assertIsBool($heartbeat['alive']);
        }
    }

    public function test_recent_failed_jobs_returns_expected_shape(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'transmission',
            'payload' => '{}',
            'exception' => "EIS timeout\nStack trace",
            'failed_at' => now(),
        ]);

        $failed = app(QueueMonitor::class)->recentFailedJobs();

        $this->assertCount(1, $failed);
        $this->assertSame('transmission', $failed[0]['queue']);
        $this->assertSame('EIS timeout', $failed[0]['exception']);
        $this->assertArrayHasKey('id', $failed[0]);
        $this->assertArrayHasKey('failed_at', $failed[0]);
    }
}
