<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureFleetAuth;
use App\Models\FleetTask;
use App\Services\Fleet\FleetOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FleetTaskController extends Controller
{
    public function __construct(
        private readonly FleetOrchestrator $orchestrator,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:64',
            'targets' => 'required',
            'payload' => 'nullable|array',
        ]);

        $authSource = (string) $request->attributes->get('fleet_auth', 'unknown');

        if ($authSource === EnsureFleetAuth::AUTH_AGENT) {
            $agent = $request->attributes->get('fleet_agent');
            $validated['targets'] = $agent->agent_id;
        }

        try {
            $task = $this->orchestrator->createTask(
                $validated['command'],
                $validated['targets'],
                $validated['payload'] ?? [],
                $authSource,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(
            $this->orchestrator->aggregateResults($task),
            201
        );
    }

    public function show(string $taskId): JsonResponse
    {
        $task = FleetTask::query()->findOrFail($taskId);

        return response()->json($this->orchestrator->aggregateResults($task));
    }

    public function index(Request $request): JsonResponse
    {
        $tasks = FleetTask::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (FleetTask $task) => $this->orchestrator->aggregateResults($task));

        return response()->json(['data' => $tasks]);
    }
}
