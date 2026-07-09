<?php

use App\Http\Middleware\AllowInertiaCaching;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureSupportedLocales;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\OnlyAdmin;
use App\Http\Middleware\OnlyUnverified;
use App\Jobs\CleanTemporaryUploads;
use App\Jobs\GenerateSitemapRobotsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web([
            EnsureSupportedLocales::class,
            HandleInertiaRequests::class,
            AllowInertiaCaching::class,
        ]);

        // redirect guests/unauthenticated users to the login page
        $middleware->redirectGuestsTo(function (Request $request) {
            $pathWithSlash = '/' . $request->path();
            
            return route('login', [
                // Pass the locale manually since the EnsureSupportedLocales middleware isn't run
                'locale' => $request->route('locale') ?? app()->getLocale(),
                'redirect_url' => $pathWithSlash
            ]);
        });

        // redirect authenticated users to the dashboard page
        $middleware->redirectUsersTo(function (Request $request) {
            // Redirect user to the redirect_url if it exists
            $redirectUrl = $request->input('redirect_url');
            $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
            if ($isPathname) {
                return $redirectUrl;
            }
            
            if ($request->user()->can('dashboard')) {
                return to('admin.dashboard.index');
            };
            return to('user.dashboard.index');
        });

        // adding custom middleware
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'not-verified' => OnlyUnverified::class,
            'admin' => OnlyAdmin::class,
        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (Symfony\Component\HttpFoundation\Response $response, Throwable $exception, Request $request) {
            if (!app()->environment(['local', 'testing']) && !$response->isRedirect()) {
                // configure inertia since the middleware doesn't run when the exception is thrown
                $inertiaConfig = new HandleInertiaRequests();
                Inertia::setRootView($inertiaConfig->rootView($request));

                return inertia('Error', ['status' => $response->getStatusCode()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            } elseif ($response->getStatusCode() === 419) {
                return back()->with([
                    'message' => 'The page expired, please try again.',
                ]);
            }

            return $response;
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new GenerateSitemapRobotsJob)->daily();
        $schedule->job(new CleanTemporaryUploads())->daily();
    })
    ->create();
