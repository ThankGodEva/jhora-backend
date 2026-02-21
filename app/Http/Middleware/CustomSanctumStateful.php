<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class CustomSanctumStateful extends EnsureFrontendRequestsAreStateful
{
    protected function shouldPassThrough($request)
    {
        // Your custom logic: skip stateful check for pure API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return true;
        }

        return parent::shouldPassThrough($request);
    }
}