<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;
use ReflectionClass;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'layouts.react';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'locale' => app()->getLocale(),
            'translations' => app('translations'),
            'supportedLocales' => app('supportedLocales'),

            // don't touch the user field it will break the app
            'user' => $request->user()?->only('id', 'name', 'email', 'google_id', 'privileges', 'avatar', 'password_last_changed'),
            'menu' => $this->getMenu($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
            ],
            'version' => $this->version($request),
        ]);
    }

    public function getMenu(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            return [];
        }

        $adminMenu = $request->routeIs('admin.*');

        // Get all files in the app/Http/Controllers directory according to current route
        $allFiles = File::allFiles(app_path('Http/Controllers') . ($adminMenu ? '/Admin' : '/User'));

        $classNames = collect($allFiles)
            ->map(function ($file) {
                // Construct the class name from the file's path
                $class = str_replace(
                    [app_path() . '/', '.php'], // Find these parts of the path...
                    ['App/', ''],               // ...and replace them with these.
                    $file->getRealPath()
                );

                // Convert directory separators to namespace separators
                return str_replace('/', '\\', $class);
            })
            ->filter(function ($class) {
                // Ensure the file represents a real, loadable class
                return class_exists($class);
            });

        // Add the SettingsController
        $settingsClass = 'App\\Http\\Controllers\\SettingsController';
        $classNames[] = $settingsClass;

        $translation = app('translations')["header"];
        $dynamicMenu = $classNames->map(function ($class) use ($translation, $user, $settingsClass) {
            $reflection = new ReflectionClass($class);
            $nameValue = null;
            $iconValue = null;
            $positionValue = 99;

            if ($reflection->hasProperty('name') && $reflection->getProperty('name')->isStatic()) {
                $nameValue = $reflection->getStaticPropertyValue('name');
            };

            if (!$nameValue) {
                return null;
            }

            // Check if the page only for admin and can be accessed
            $adminMenu = str_contains($class, '\\Admin\\');
            if ($adminMenu) {
                if (!$user->can($nameValue)) {
                    return null;
                }
            }

            if ($reflection->hasProperty('icon') && $reflection->getProperty('icon')->isStatic()) {
                $iconValue = $reflection->getStaticPropertyValue('icon');
            };

            if ($reflection->hasProperty('position') && $reflection->getProperty('position')->isStatic()) {
                $positionValue = $reflection->getStaticPropertyValue('position');
            };

            $prefix = $adminMenu ? 'admin.' : 'user.';

            // Override prefix for the specific SettingsController
            if ($class === $settingsClass) {
                $prefix = '';
            }

            return [
                'label' => $translation[$nameValue] ?? $nameValue,
                'icon' => $iconValue ?? 'LayoutDashboard',
                'link' => to("$prefix$nameValue.index"),
                'position' => $positionValue
            ];
        })->filter()->sortBy('position');

        $menu = [];
        if ($user->privileges && is_array($user->privileges)) {
            $menu[] = $adminMenu ?
                [
                    'label' => $translation['user_menu'],
                    'icon' => 'LayoutDashboard',
                    'link' => to('user.dashboard.index'),
                ] :
                [
                    'label' => $translation['admin_menu'],
                    'icon' => 'LayoutDashboard',
                    'link' => in_array('dashboard', $user->privileges) ?
                        to('admin.dashboard.index') :
                        to('admin.' . $user->privileges[0] . '.index'),
                ];
        }

        return array_merge($dynamicMenu->values()->toArray(), $menu);
    }
}
