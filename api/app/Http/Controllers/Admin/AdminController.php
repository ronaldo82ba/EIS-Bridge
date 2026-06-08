<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class AdminController extends Controller
{
    use AuthorizesRequests;

    protected function adminUser(): User
    {
        return request()->user();
    }

    protected function notImplemented(string $feature = 'This action'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => "{$feature} is not implemented yet.",
        ], 501);
    }
}
