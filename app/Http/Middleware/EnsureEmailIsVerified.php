<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */

    // this to overwrite the default verified middleware
    public function handle($request, Closure $next): RedirectResponse|Response|JsonResponse|BinaryFileResponse|StreamedResponse
    {
        $pathWithSlash = '/' . $request->path();
        
        if (
            ! $request->user() ||
            ($request->user() instanceof MustVerifyEmail &&
                ! $request->user()->hasVerifiedEmail())
        ) {
            return $request->expectsJson()
                ? abort(403, 'Your email address is not verified.')
                : go_to('verification.notice', [
                    'redirect_url' => $pathWithSlash,
                ]);
        }

        return $next($request);
    }
}
