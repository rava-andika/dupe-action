<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CompetitionsExport;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CompetitionsController extends Controller
{
    public static string $name = 'competitions';
    public static string $icon = "Trophy";
    public static int $position = 1;

    private array $allowedColumns = ['id', 'name', 'description', 'short_desc', 'image', 'guidebook', 'price', 'contacts', 'max_team_size', 'min_team_size','enforce_single_team_rule', 'timeline'];
    private array $fillableColumns = ['name', 'description', 'short_desc', 'image', 'guidebook', 'price', 'contacts', 'max_team_size', 'min_team_size','enforce_single_team_rule', 'timeline'];

    private function getPaginatedData(Request $request): array
    {
        return
            getPaginatedData(
                $request,
                Competition::class,
                ['id', 'name', 'description', 'short_desc', 'price'],
                'admin.competitions.index',
            );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/Competitions', $this->getPaginatedData($request));
    }

    public function edit(Request $request, Competition $competition): Response
    {
        return inertia('Admin/Competitions', array_merge($this->getPaginatedData($request), [
            'editData' => $competition
                ->only($this->allowedColumns),
        ]));
    }

    public function show(Request $request, Competition $competition): Response
    {
        return inertia('Admin/Competitions', array_merge($this->getPaginatedData($request), [
            'showData' => $competition
                ->only($this->allowedColumns),
        ]));
    }

    public function update(Request $request, Competition $competition): RedirectResponse
    {
        $dataToUpdate = $this->validateAndStoreFile($request, $competition);
        $competition->update($dataToUpdate);
        return back()->with('success', $dataToUpdate["name"] . " updated successfully");
    }

    public function create(Request $request): Response
    {
        $array_exclude_enforce = array_diff($this->fillableColumns, ['enforce_single_team_rule']);
        return inertia('Admin/Competitions', array_merge($this->getPaginatedData($request), [
            'createData' => array_merge(
                array_fill_keys($array_exclude_enforce, ''),
                ['enforce_single_team_rule' => false]
            )
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $dataToUpdate = $this->validateAndStoreFile($request, new Competition());
        Competition::create($dataToUpdate);
        return back()->with('success', $dataToUpdate["name"] . " created successfully");
    }

    public function destroy(Competition $competition): RedirectResponse
    {
        $competition->delete();
        return back()->with("success", $competition->name . " deleted successfully");
    }

    public function restore(Competition $competition): RedirectResponse
    {
        $competition->restore();
        return back()->with("success", $competition->name . " restore successfully");
    }

    public function deleteBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:competitions,id'
        ]);
        Competition::whereIn('id', $validated['ids'])
            ->get()
            ->each(fn ($competition) => $competition->delete());
        return back()->with("success", "Competitions deleted successfully");
    }

    public function restoreBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:competitions,id'
        ]);
        Competition::onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(fn ($competition) => $competition->restore());
        return back()->with("success", "Competitions restored successfully");
    }

    public function exportBulk(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:competitions,id'
        ]);
        $filename = 'competitions-' . now()->format('Y-m-d') . '.csv';
        admin_log('export-bulk', self::$name, "Exported " . count($validated['ids']) . " competitions");
        return Excel::download(new CompetitionsExport($validated['ids']), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    private function validateAndStoreFile(Request $request, Competition $competition): array
    {
        $rules = [
            'name' => 'required|string|unique:competitions,name,' . $competition->id,
            'description' => 'required|array',
            'short_desc' => 'required|array',
            'image' => 'required|string',
            'guidebook' => 'required|string',
            'price' => 'required|numeric',
            'contacts' => 'required|array',
            'contacts.*.name' => 'required|string',
            'contacts.*.phoneNumber' => 'required|numeric',
            'max_team_size' => 'required|numeric',
            'min_team_size' => 'required|numeric',
            'enforce_single_team_rule' => 'required|boolean',
            'timeline' => 'required|array',
            'timeline.*.start' => 'required|date',
            'timeline.*.end' => 'required|date|after_or_equal:timeline.*.start',
            'timeline.*.description' => 'required|string',
            'timeline.*.is_registration' => 'required|boolean',
            'timeline.*.is_submission' => 'required|boolean',
        ];

        // Define the custom names for your attributes.
        // This makes the ":attribute" placeholder in error messages look nice.
        $customAttributes = [
            'short_desc' => 'Short Description',
            'max_team_size' => 'Maximum Team Size',
            'min_team_size' => 'Minimum Team Size',
            'enforce_single_team_rule' => 'Enforce Single Team Rule',
            'contacts.*.name' => 'Contact Name',
            'contacts.*.phoneNumber' => 'Contact Phone Number',
            'timeline.*.start' => 'Timeline Start Date',
            'timeline.*.end' => 'Timeline End Date',
            'timeline.*.description' => 'Timeline Description',
            'timeline.*.is_registration' => 'Is Registration',
            'timeline.*.is_submission' => 'Is Submission',
        ];

        foreach (app('supportedLocales')['active'] as $locale) {
            // Add the validation rule for the current locale
            $rules["description.$locale"] = 'required|string';
            $rules["short_desc.$locale"] = 'required|string';

            // Add the corresponding custom attribute name for that locale
            $customAttributes["description.$locale"] = "Description ($locale)";
            $customAttributes["short_desc.$locale"] = "Short Description ($locale)";
        }

        $request->validate($rules, [], $customAttributes);

        $dataToUpdate = $request->only($this->fillableColumns);
        syncFileStorage($competition, $dataToUpdate, 'image', 'images/competitions');
        syncFileStorage($competition, $dataToUpdate, 'guidebook', 'documents/competitions');
        return $dataToUpdate;
    }
}
