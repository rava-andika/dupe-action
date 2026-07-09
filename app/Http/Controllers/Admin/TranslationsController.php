<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class TranslationsController extends Controller
{
    public static string $name = 'translations';
    public static string $icon = "Languages";

    private $allowedColumns = ['id', 'group', 'key', 'value'];
    private $fillableColumns = ['group', 'key', 'value'];

    private function getPaginatedData(Request $request): array
    {
        $locale = $request->input('locale', app('supportedLocales')['active'][0]);

        return array_merge(
            getPaginatedData(
                $request,
                Translation::class,
                $this->allowedColumns,
                'admin.translations.index',
                function ($query) use ($locale) {
                    return $query->where('locale', $locale);
                },
                ['locale' => $locale]
            ),
            [
                'localeMenu' => $locale,
            ]
        );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/Translations', $this->getPaginatedData($request));
    }

    public function edit(Request $request,  Translation $translation): Response
    {
        return inertia('Admin/Translations', array_merge($this->getPaginatedData($request), [
            'editData' => $translation
                ->only($this->allowedColumns),
        ]));
    }

    public function show(Request $request,  Translation $translation): Response
    {
        return inertia('Admin/Translations', array_merge($this->getPaginatedData($request), [
            'showData' => $translation
                ->only($this->allowedColumns),
        ]));
    }

    public function update(Request $request, Translation $translation): RedirectResponse
    {
        $this->validate($request);
        $translation->update($request->only($this->fillableColumns));
        return back()->with('success', "Translation updated successfully");
    }

    public function create(Request $request): Response
    {
        return inertia('Admin/Translations', array_merge($this->getPaginatedData($request), [
            'createData' => ["locale" => ""],
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => 'required|string',
        ]);

        if (Translation::where('locale', $request->locale)->first()) {
            return back()->withErrors(['locale' => 'Locale already exists']);
        }
        $originalLocale = app('supportedLocales')['active'][0];
        $originalTranslations = Translation::where('locale', $originalLocale)->get();

        // Clone each translation and change the locale
        $cloned = $originalTranslations->map(function ($item) use ($request) {
            $new = $item->replicate();
            $new->locale = $request->locale;
            $new->is_active = false;
            return $new;
        });

        // Insert them all into the database
        Translation::insert($cloned->toArray());

        Cache::forget('supportedLocales');
        admin_log('create', self::$name, "Locale: {$request->locale}");
        return back()->with('success', "Translations created successfully");
    }

    public function toggleActiveLocale(Request $request): RedirectResponse
    {
        $locale = $request->locale;
        
        // handle activate or deactivate locale
        $translation = Translation::where('locale', $locale)->first();

        if (!$translation) {
            return back()->with('error', 'Locale not found');
        }

        $currentStatus = $translation->is_active;

        // Ensure atleast one locale is active
        if($currentStatus && count(app('supportedLocales')['active']) <= 1) {
            return back()->with('error', 'At least one locale must be active');
        }

        Translation::where('locale', $locale)
            ->update(['is_active' => !$currentStatus]);

        // clear cache to update the status locale
        Cache::forget('supportedLocales');
        admin_log('toggle-activate-locale', self::$name, "Locale: {$locale} Status: {$currentStatus} -> " . !$currentStatus);
        return back()->with('success', "Translations updated successfully");
    }

    private function validate(Request $request): array
    {
        return $request->validate([
            'group' => 'required|string',
            'key' => 'required|string',
            'value' => 'required|string',
        ]);
    }
}
