<?php

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

// make it into function so can be rebind in the test
return function () {
    if (!Schema::hasTable('translations')) return;
    
    // assign function to cache translation
    app()->singleton('translations', function () {
        $locale = app()->getLocale();
        $groups = Translation::where('locale', $locale)
            ->select('group')
            ->distinct()
            ->pluck('group');
        $all_translations = [];

        foreach ($groups as $group) {
            $translation = Cache::rememberForever("translations.$locale.$group", function () use ($locale, $group) {
                return Translation::where('locale', $locale)
                    ->where('group', $group)
                    ->pluck('value', 'key')
                    ->toArray();
            });
            $all_translations[$group] = $translation;
        };

        return $all_translations;
    });

    // make global variable
    app()->singleton('supportedLocales', function () {
        return Cache::rememberForever('supportedLocales', function () {
            $locales = Translation::select('locale', 'is_active')
                ->distinct()
                ->get()
                ->groupBy('is_active')
                ->map(function ($group) {
                    return $group->pluck('locale')->values()->toArray();
                });

            return [
                'active' => $locales[1] ?? [],
                'inactive' => $locales[0] ?? [],
            ];
        });
    });
};