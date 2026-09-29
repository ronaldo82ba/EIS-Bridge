<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    if ($request->expectsJson()) {
        return response()->json([
            'service' => config('app.name'),
            'api' => url('/v1'),
            'health' => url('/v1/health'),
            'admin' => url('/admin'),
            'documentation' => 'https://eisbridge.com/portal/',
        ]);
    }

    return redirect('/admin');
});

Route::view('/admin/{any?}', 'admin')->where('any', '.*');
