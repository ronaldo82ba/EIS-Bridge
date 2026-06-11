<?php

namespace App\Services\Fleet;

use App\Models\FleetAgent;
use App\Models\FleetTaskResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FleetAgentDispatcher
{
    public function __construct(
        private readonly FleetTokenService $tokenService,
    ) {}

    public function dispatch(FleetTaskResult $result): FleetTaskResult
    {
        $result->load(['task', 'agent']);
        $agent = $result->agent;
        $task = $result->task;

        if (! $agent instanceof FleetAgent || ! $task) {
            $result->update([
                'status' => 'failed',
                'error_message' => 'Fleet agent or task not found.',
                'completed_at' => now(),
            ]);

            return $result;
        }

        $result->update([
            'status' => 'running',
            'started_at' => now(),
        ]);

        $start = microtime(true);

        if ($agent->callback_base_url) {
            $response = $this->callAgentEndpoint($agent, $task->command, $task->payload ?? []);

            if ($response !== null) {
                return $this->finalizeResult($result, $response, $start);
            }
        }

        $result->update([
            'status' => 'pending',
            'started_at' => null,
        ]);

        return $result;
    }

    public function applyAgentResponse(FleetTaskResult $result, array $response, ?string $error = null): FleetTaskResult
    {
        $start = $result->started_at?->getTimestamp() ?? time();

        return $this->finalizeResult($result, [
            'success' => $error === null,
            'data' => $response,
            'error' => $error,
        ], (float) $start);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function callAgentEndpoint(FleetAgent $agent, string $command, array $payload): ?array
    {
        $baseUrl = rtrim($agent->callback_base_url, '/');
        $url = "{$baseUrl}/{$command}";
        $token = config('fleet.agent_token_header', 'X-Agent-Token');

        try {
            $response = Http::timeout(config('fleet.task_timeout_sec', 60))
                ->withHeaders([$token => $this->resolvePlainTokenForAgent($agent)])
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'error' => null,
                ];
            }

            return [
                'success' => false,
                'data' => $response->json(),
                'error' => $response->json('message') ?? $response->body(),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Fleet agent callback failed; leaving task pending for poll.', [
                'agent_id' => $agent->agent_id,
                'command' => $command,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function finalizeResult(FleetTaskResult $result, array $response, float $start): FleetTaskResult
    {
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $success = (bool) ($response['success'] ?? false);

        $result->update([
            'status' => $success ? 'completed' : 'failed',
            'response_payload' => $response['data'] ?? null,
            'error_message' => $success ? null : (string) ($response['error'] ?? 'Agent command failed.'),
            'duration_ms' => $durationMs,
            'completed_at' => now(),
        ]);

        return $result->fresh();
    }

    private function resolvePlainTokenForAgent(FleetAgent $agent): string
    {
        return $this->tokenService->decryptToken($agent) ?? '';
    }
}
