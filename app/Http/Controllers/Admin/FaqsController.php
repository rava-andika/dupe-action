<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FaqsExport;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FaqsController extends Controller
{
    public static string $name = 'faqs';
    public static string $icon = "HelpCircle";

    private $fillableColumns = ['question', 'competition_id', 'answer'];

    private function getPaginatedData(Request $request): array
    {
        return array_merge(
            getPaginatedData(
                $request,
                Faq::class,
                ['id', 'question', 'competition.name', 'answer'],
                'admin.faqs.index',
            ),
            [
                // using the same cache from app/Http/Controllers/StaticController.php
                'competitions'  => Cache::rememberForever('competitions', fn() => Competition::select('id', 'name', 'updated_at')->get())
                    ->pluck('name', 'id')
            ]
        );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/Faqs', $this->getPaginatedData($request));
    }

    public function edit(Request $request, Faq $faq): Response
    {
        return inertia('Admin/Faqs', array_merge($this->getPaginatedData($request), [
            'editData' => $faq
                ->only(["id", ...$this->fillableColumns]),
        ]));
    }

    public function show(Request $request, Faq $faq): Response
    {
        return inertia('Admin/Faqs', array_merge($this->getPaginatedData($request), [
            'showData' => $faq
                ->only(["id", ...$this->fillableColumns]),
        ]));
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validate($request));
        return back()->with('success', "FAQ updated successfully");
    }

    public function create(Request $request): Response
    {
        return inertia('Admin/Faqs', array_merge($this->getPaginatedData($request), [
            'createData' => array_fill_keys($this->fillableColumns, ''),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create($this->validate($request));
        return back()->with('success', "FAQ created successfully");
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();
        return back()->with("success", "FAQ deleted successfully");
    }

    public function restore(Faq $faq): RedirectResponse
    {
        $faq->restore();
        return back()->with("success", "FAQ restored successfully");
    }

    public function deleteBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:faqs,id'
        ]);
        Faq::whereIn('id', $validated['ids'])->delete();
        Cache::forget('faq');
        admin_log('delete-bulk', self::$name, "Deleted " . count($validated['ids']) . " FAQs");
        return back()->with("success", "FAQs deleted successfully");
    }

    public function restoreBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:faqs,id'
        ]);
        Faq::whereIn('id', $validated['ids'])->restore();
        Cache::forget('faq');
        admin_log('restore-bulk', self::$name, "Restored " . count($validated['ids']) . " FAQs");
        return back()->with("success", "FAQs restored successfully");
    }

    public function exportBulk(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:faqs,id'
        ]);
        $filename = 'faqs-' . now()->format('Y-m-d') . '.csv';
        admin_log('export-bulk', self::$name, "Exported " . count($validated['ids']) . " FAQs");
        return Excel::download(new FaqsExport($validated['ids']), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    private function validate(Request $request): array
    {
        $rules = [
            'question' => 'required|array',
            'competition_id' => 'nullable|integer|exists:competitions,id',
            'answer' => 'required|array',
        ];

        // Define the custom names for your attributes.
        // This makes the ":attribute" placeholder in error messages look nice.
        $customAttributes = [
            "competition_id" => "Competition Name",
        ];

        foreach (app('supportedLocales')['active'] as $locale) {
            // Add the validation rule for the current locale
            $rules["question.$locale"] = 'required|string';
            $rules["answer.$locale"] = 'required|string';

            // Add the corresponding custom attribute name for that locale
            $customAttributes["question.$locale"] = "Question ($locale)";
            $customAttributes["answer.$locale"] = "Answer ($locale)";
        }

        $request->validate($rules, [], $customAttributes);
        return $request->only($this->fillableColumns);
    }
}
