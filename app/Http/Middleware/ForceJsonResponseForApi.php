<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponseForApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        // If unauthenticated, force JSON 401 instead of redirect
        if ($response->getStatusCode() === 302 && $request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $response;
    }
}