<?php

namespace App\Services\Fleet;

use App\Models\FleetAgent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FleetTokenService
{
    public function generateAgentToken(): string
    {
        return 'fa_'.Str::random(48);
    }

    public function hashToken(string $token): string
    {
        return Hash::make($token);
    }

    public function encryptToken(string $token): string
    {
        return Crypt::encryptString($token);
    }

    public function decryptToken(FleetAgent $agent): ?string
    {
        if ($agent->token_encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($agent->token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function validateAgentToken(string $token): ?FleetAgent
    {
        $agents = FleetAgent::query()->get();

        foreach ($agents as $agent) {
            if (Hash::check($token, $agent->token_hash)) {
                return $agent;
            }
        }

        return null;
    }

    public function validateCommanderToken(?string $token): bool
    {
        $expected = config('fleet.commander_token');

        if ($expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function validateOperatorKey(?string $key): bool
    {
        $expected = config('fleet.operator_key');

        if ($expected === '' || $key === null || $key === '') {
            return false;
        }

        return hash_equals($expected, $key);
    }
}
