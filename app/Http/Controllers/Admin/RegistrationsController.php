<?php

namespace App\Http\Controllers\Admin;

use App\Exports\RegistrationsExport;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Notifications\RegistrationTeamStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RegistrationsController extends Controller
{
    public static string $name = 'registrations';
    public static string $icon = "UserPlus";

    private function getPaginatedData(Request $request): array
    {
        $status = $request->input('status', 'pending');

        return array_merge(
            getPaginatedData(
                $request,
                Registration::class,
                ['id', 'team.name', 'competition.name', 'reviewer.name', 'submitted_at'],
                'admin.registrations.index',
                function ($query) use ($status) {
                    return $query->where('status', $status);
                },
                ['status' => $status]
            ),
            [
                'statusMenu' => $status,
            ]
        );
    }

    public function index(Request $request): RedirectResponse|Response
    {
        if (!$request->has('status')) {
            return go_to('admin.registrations.index', ['status' => 'pending']);
        }
        return inertia('Admin/Registrations', $this->getPaginatedData($request));
    }

    public function edit(Request $request,  Registration $registration): Response
    {
        return inertia('Admin/Registrations', array_merge($this->getPaginatedData($request), [
            'editData' => $registration
                ->only('id', 'status', 'notes', 'group_link'),
        ]));
    }

    public function show(Request $request,  Registration $registration): Response
    {
        $registration->load('team', 'competition', 'reviewer');
        return inertia('Admin/Registrations', array_merge($this->getPaginatedData($request), [
            'showData' => [
                'id' => $registration->id,
                'team' =>  empty($registration->team) ? null : [$registration->team_id => $registration->team?->name],
                'competition' => empty($registration->competition) ? null : [$registration->competition_id => $registration->competition->name],
                'payment_proof' => $registration->payment_proof,
                'status' => $registration->status,
                'notes' => $registration->notes,
                'group_link' => $registration->group_link,
                'reviewed_by' =>  empty($registration->reviewer) ? null : [$registration->reviewed_by => $registration->reviewer->name],
                'submitted_at' => $registration->submitted_at,
            ],
        ]));
    }

    public function update(Request $request, Registration $registration): RedirectResponse
    {
        $statusMenu = $request->query('status');
        if ($statusMenu !== 'pending') {
            return back()->with('error', 'You can only update pending registrations');
        }

        $status = $request->status;
        $notesRule = $status === 'rejected' ? 'required' : 'nullable';
        $groupLinkRule = $status === 'approved' ? 'required' : 'nullable';
        $rules = [
            'status' => 'required|in:approved,rejected',
            'notes' =>   $notesRule . '|array',
            'group_link' => $groupLinkRule . '|url',
        ];

        // Define the custom names for your attributes.
        $customAttributes = [];

        foreach (app('supportedLocales')['active'] as $locale) {
            // Add the validation rule for the current locale
            $rules["notes.$locale"] =  $notesRule . '|string';

            // Add the corresponding custom attribute name for that locale
            $customAttributes["notes.$locale"] = "Notes ($locale)";
        }

        $dataToUpdate = $request->validate($rules, [], $customAttributes);
        $registration->update(
            array_merge(
                $dataToUpdate,
                ['reviewed_by' => $request->user()->id]
            )
        );
        Notification::send($registration->team->members, new RegistrationTeamStatus($registration));
        return back()->with('success', "Registration updated successfully");
    }

    public function exportBulk(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:registrations,id'
        ]);
        $filename = 'registrations-' . now()->format('Y-m-d') . '.csv';
        admin_log('export-bulk', self::$name, "Exported " . count($validated['ids']) . " registrations");
        return Excel::download(new RegistrationsExport($validated['ids']), $filename, \Maatwebsite\Excel\Excel::CSV);
    }
}
