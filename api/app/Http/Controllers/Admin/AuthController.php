<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends AdminController
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->hasAdminAccess()) {
            return response()->json([
                'message' => 'You do not have access to the admin panel.',
            ], 403);
        }

        Auth::login($user);

        $expirationMinutes = config('sanctum.expiration');
        $expiresAt = is_numeric($expirationMinutes)
            ? now()->addMinutes((int) $expirationMinutes)
            : null;

        $token = $user->createToken('admin-panel', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->toAdminArray(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->toAdminArray(),
        ]);
    }
}
