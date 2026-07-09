<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AllowInertiaCaching
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if the request is an Inertia GET request, have Is-Preloading header set to true and if the response was successful.
        if ($request->isMethod('GET') && $request->header('X-Inertia') && ($request->header('Is-Preloading') === 'true') && $response->isSuccessful()) {
            // If so, set the Cache-Control header to to allow caching for 10 seconds.
            $response->headers->set('Cache-Control', 'public, max-age=10');
        }

        return $response;
    }
}
