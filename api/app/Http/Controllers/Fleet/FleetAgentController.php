<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Models\FleetAgent;
use App\Models\FleetTaskResult;
use App\Services\Fleet\FleetAgentDispatcher;
use App\Services\Fleet\FleetOrchestrator;
use App\Services\Fleet\FleetTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetAgentController extends Controller
{
    public function __construct(
        private readonly FleetTokenService $tokenService,
        private readonly FleetOrchestrator $orchestrator,
        private readonly FleetAgentDispatcher $dispatcher,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent_id' => 'required|string|max:128',
            'device_serial' => 'nullable|string|max:128',
            'device_model' => 'nullable|string|max:128',
            'callback_base_url' => 'nullable|url|max:512',
        ]);

        $plainToken = $this->tokenService->generateAgentToken();

        $agent = FleetAgent::updateOrCreate(
            ['agent_id' => $validated['agent_id']],
            [
                'device_serial' => $validated['device_serial'] ?? null,
                'device_model' => $validated['device_model'] ?? null,
                'callback_base_url' => $validated['callback_base_url'] ?? null,
                'token_hash' => $this->tokenService->hashToken($plainToken),
                'token_encrypted' => $this->tokenService->encryptToken($plainToken),
                'status' => 'online',
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'agent_id' => $agent->agent_id,
            'token' => $plainToken,
            'registered_at' => $agent->updated_at?->toIso8601String(),
        ], $agent->wasRecentlyCreated ? 201 : 200);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        /** @var FleetAgent $agent */
        $agent = $request->attributes->get('fleet_agent');

        $validated = $request->validate([
            'status' => 'nullable|array',
            'callback_base_url' => 'nullable|url|max:512',
        ]);

        $agent->update([
            'status' => 'online',
            'last_seen_at' => now(),
            'last_status' => $validated['status'] ?? $agent->last_status,
            'callback_base_url' => $validated['callback_base_url'] ?? $agent->callback_base_url,
        ]);

        return response()->json([
            'agent_id' => $agent->agent_id,
            'status' => 'online',
            'last_seen_at' => $agent->last_seen_at?->toIso8601String(),
        ]);
    }

    public function pendingTasks(Request $request): JsonResponse
    {
        /** @var FleetAgent $agent */
        $agent = $request->attributes->get('fleet_agent');

        $results = FleetTaskResult::query()
            ->with('task')
            ->where('fleet_agent_id', $agent->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $results->map(fn (FleetTaskResult $result) => [
                'result_id' => $result->id,
                'command' => $result->task?->command,
                'payload' => $result->request_payload,
                'created_at' => $result->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function submitResult(Request $request, string $resultId): JsonResponse
    {
        /** @var FleetAgent $agent */
        $agent = $request->attributes->get('fleet_agent');

        $result = FleetTaskResult::query()
            ->where('id', $resultId)
            ->where('fleet_agent_id', $agent->id)
            ->firstOrFail();

        $validated = $request->validate([
            'success' => 'required|boolean',
            'response' => 'nullable|array',
            'error' => 'nullable|string|max:2000',
        ]);

        $this->dispatcher->applyAgentResponse(
            $result,
            $validated['response'] ?? [],
            $validated['success'] ? null : ($validated['error'] ?? 'Agent reported failure.')
        );

        if ($result->task) {
            $this->orchestrator->refreshTaskStatus($result->task);
        }

        return response()->json([
            'result_id' => $result->id,
            'status' => $result->fresh()->status,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $agents = FleetAgent::query()
            ->orderBy('agent_id')
            ->get()
            ->map(fn (FleetAgent $agent) => [
                'agent_id' => $agent->agent_id,
                'device_serial' => $agent->device_serial,
                'device_model' => $agent->device_model,
                'status' => $agent->status,
                'callback_base_url' => $agent->callback_base_url,
                'last_status' => $agent->last_status,
                'last_seen_at' => $agent->last_seen_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $agents]);
    }
}
