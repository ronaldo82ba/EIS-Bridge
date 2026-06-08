<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function index()
    {
        $queues = DB::table('jobs')
            ->selectRaw('queue, COUNT(*) as pending')
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $failedByQueue = DB::table('failed_jobs')
            ->selectRaw('queue, COUNT(*) as failed')
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $names = collect(['mapping', 'signing', 'transmission', 'retry', 'default'])
            ->merge($queues->keys())
            ->merge($failedByQueue->keys())
            ->unique()
            ->values();

        $queueStats = $names->map(fn (string $name) => [
            'name' => $name,
            'pending' => (int) ($queues[$name]->pending ?? 0),
            'failed' => (int) ($failedByQueue[$name]->failed ?? 0),
        ]);

        return response()->json([
            'pending_count' => DB::table('jobs')->count(),
            'failed_count' => DB::table('failed_jobs')->count(),
            'queues' => $queueStats,
        ]);
    }

    public function failed(Request $request)
    {
        $jobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->paginate($request->integer('per_page', 25));

        $jobs->getCollection()->transform(function ($job) {
            $payload = json_decode($job->payload, true) ?? [];

            return [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'queue' => $job->queue,
                'display_name' => $payload['displayName'] ?? null,
                'exception' => strtok($job->exception, "\n"),
                'failed_at' => $job->failed_at,
            ];
        });

        return response()->json($jobs);
    }

    public function retry(int $id)
    {
        $job = DB::table('failed_jobs')->where('id', $id)->first();

        if (! $job) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Failed job not found.',
            ], 404);
        }

        Artisan::call('queue:retry', ['id' => [$job->uuid]]);

        return response()->json([
            'status' => 'success',
            'message' => 'Job queued for retry.',
            'job_id' => $id,
        ]);
    }

    public function destroy(int $id)
    {
        $deleted = DB::table('failed_jobs')->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Failed job not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Failed job deleted.',
        ]);
    }
}
