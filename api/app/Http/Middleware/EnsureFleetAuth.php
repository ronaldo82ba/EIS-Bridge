<?php

namespace App\Http\Middleware;

use App\Services\Fleet\FleetTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFleetAuth
{
    public const AUTH_COMMANDER = 'commander';

    public const AUTH_AGENT = 'agent';

    public const AUTH_OPERATOR = 'operator';

    public function __construct(
        private readonly FleetTokenService $tokenService,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'mutate'): Response
    {
        $commanderHeader = config('fleet.commander_token_header', 'X-Commander-Token');
        $agentHeader = config('fleet.agent_token_header', 'X-Agent-Token');
        $operatorHeader = config('fleet.operator_key_header', 'X-Operator-Key');

        $commanderToken = $request->header($commanderHeader);
        $agentToken = $request->header($agentHeader);
        $operatorKey = $request->header($operatorHeader);

        if ($mode === 'dashboard') {
            if ($this->tokenService->validateOperatorKey($operatorKey)) {
                $request->attributes->set('fleet_auth', self::AUTH_OPERATOR);

                return $next($request);
            }

            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Missing or invalid operator key.',
            ], 401);
        }

        if ($this->tokenService->validateCommanderToken($commanderToken)) {
            $request->attributes->set('fleet_auth', self::AUTH_COMMANDER);

            return $next($request);
        }

        if ($agentToken) {
            $agent = $this->tokenService->validateAgentToken($agentToken);

            if ($agent) {
                $request->attributes->set('fleet_auth', self::AUTH_AGENT);
                $request->attributes->set('fleet_agent', $agent);

                if ($mode === 'agent-only') {
                    return $next($request);
                }

                if ($mode === 'mutate' && ! $this->agentMayMutate($request, $agent->agent_id)) {
                    return response()->json([
                        'error' => 'forbidden',
                        'message' => 'Agent token may only control its own device.',
                    ], 403);
                }

                if (in_array($mode, ['read', 'mutate'], true)) {
                    return $next($request);
                }
            }
        }

        if ($this->tokenService->validateOperatorKey($operatorKey)) {
            $request->attributes->set('fleet_auth', self::AUTH_OPERATOR);

            return $next($request);
        }

        return response()->json([
            'error' => 'unauthorized',
            'message' => 'Missing or invalid fleet credentials.',
        ], 401);
    }

    private function agentMayMutate(Request $request, string $agentId): bool
    {
        $targets = $request->input('targets', $request->input('agent_id'));

        if ($targets === null) {
            return true;
        }

        if ($targets === 'ALL') {
            return false;
        }

        $ids = is_array($targets) ? $targets : [$targets];

        return count($ids) === 1 && $ids[0] === $agentId;
    }
}
