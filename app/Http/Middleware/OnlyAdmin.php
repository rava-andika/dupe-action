<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

class OnlyAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (!$user || !$route) {
            return abort(403, 'You do not have permission to access this page.');
        }

        // get the controller action
        // e.g: "App\Http\Controllers\CompetitionsController@index"
        $action = $route->getActionName();

        // if only closoure, skip
        if (str_contains($action, '@') === false) {
            return $next($request);
        }

        // Take the class part
        $controllerClass = explode('@', $action)[0];

        if (class_exists($controllerClass)) {
            try {
                $reflection = new ReflectionClass($controllerClass);

                if ($reflection->hasProperty('name') && $reflection->getProperty('name')->isStatic()) {
                    $permissionName = $reflection->getStaticPropertyValue('name');
                    if ($user->can($permissionName)) {
                        return $next($request);
                    }
                }
            } catch (\ReflectionException $e) {
                return abort(500, 'Server Configuration Error');
            }
        }
        return abort(403, 'You do not have permission to access this page.');
    }
}
