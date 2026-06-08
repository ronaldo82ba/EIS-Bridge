<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            User::query()->orderBy('name')->paginate($request->integer('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:super_admin,vendor_admin,support'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'vendor_id' => $data['vendor_id'] ?? null,
        ]);

        AuditLogger::log($request->user(), 'created', 'user', $user->id, [
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'in:super_admin,vendor_admin,support'],
            'vendor_id' => ['nullable', 'exists:vendors,id'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $old = $user->only(array_keys($data));
        $user->update($data);

        AuditLogger::log($request->user(), 'updated', 'user', $user->id, [
            'old' => $old,
            'new' => $user->only(array_keys($data)),
        ]);

        return response()->json($user->fresh());
    }
}
