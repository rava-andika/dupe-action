<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupportedLocales
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $urlLocale = $request->route('locale');
        
        if ($urlLocale) {
            // Handle locale from the URL. It must be valid or it's a 404.
            if (!in_array($urlLocale, app('supportedLocales')['active'])) {
                abort(404);
            }

            // If valid, set it as the app locale
            app()->setLocale($urlLocale);
            app()->forgetInstance('translations'); // Clear cache if locale changes
            $request->route()->forgetParameter('locale');
        } else {
            // No locale in the URL, so we check the session.
            $sessionLocale = Session::get('locale');

            // Use the session locale ONLY if it's still valid and supported.
            if ($sessionLocale && in_array($sessionLocale, app('supportedLocales')['active'])) {
                app()->setLocale($sessionLocale);
            }
            // If the session locale is invalid or missing, we do nothing.
        }

        // Finally, update the session with the determined (and now valid) locale.
        Session::put('locale', app()->getLocale());
        return $next($request);
    }
}
