<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204, $this->headers());
        }

        $response = $next($request);

        foreach ($this->headers() as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }

    private function headers(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, X-SANDBOX-API-KEY',
        ];
    }
}
