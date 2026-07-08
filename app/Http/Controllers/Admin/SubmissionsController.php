<?php

namespace App\Http\Controllers\Admin;

use App\Exports\SubmissionsExport;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionsController extends Controller
{
    public static string $name = 'submissions';
    public static string $icon = "Upload";


    private function getPaginatedData(Request $request): array
    {
        $competitions = Cache::rememberForever('competitions', fn() => Competition::select('id', 'name', 'updated_at')
            ->get());

        $status = $request->input('status', 'pending');

        // Create a fast lookup map using the competition name as the key. This is an O(1) operation instead of an O(n) search.
        $competitionsByName = $competitions->keyBy('name');

        // Get competition name from request, default to the first one's name
        $competitionName = $request->input('competition_name', $competitions->first()->name ?? null);

        // Find the ID instantly using the map. This is safe and returns null if not found.
        $competitionId = $competitionsByName->get($competitionName)?->id;

        return array_merge(
            getPaginatedData(
                $request,
                Submission::class,
                ['id', 'team.name', 'competition.name', 'reviewer.name', 'submitted_at'],
                'admin.submissions.index',
                function ($query, $baseTable) use ($status, $competitionId) {
                    $query->where('status', $status);
                    
                    // needed baseTable because already use relation for get name of competition, so the query will not work if not specified
                    $query->where("$baseTable.competition_id", $competitionId);

                    return $query;
                },
                [
                    'status' => $status,
                    'competition_name' => $competitionName
                ]
            ),
            [
                'statusMenu' => $status,
                'competitionMenu' => $competitionName,
                'competitions' => $competitions->pluck('name'),
            ]
        );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/Submissions', $this->getPaginatedData($request));
    }

    public function edit(Request $request,  Submission $submission): Response
    {
        return inertia('Admin/Submissions', array_merge($this->getPaginatedData($request), [
            'editData' => $submission
                ->only('id', 'feedback'),
        ]));
    }

    public function show(Request $request,  Submission $submission): Response
    {
        $submission->load('team', 'competition', 'reviewer');
        return inertia('Admin/Submissions', array_merge($this->getPaginatedData($request), [
            'showData' => [
                'id' => $submission->id,
                'team' =>  empty($submission->team) ? null : [$submission->team_id => $submission->team?->name],
                'competition' => empty($submission->competition) ? null : [$submission->competition_id => $submission->competition->name],
                'submission' => $submission->submission,
                'status' => $submission->status,
                'feedback' => $submission->feedback,
                'reviewed_by' =>  empty($submission->reviewer) ? null : [$submission->reviewed_by => $submission->reviewer->name],
                'submitted_at' => $submission->submitted_at,
            ],
        ]));
    }

    public function update(Request $request, Submission $submission): RedirectResponse
    {
        $dataToUpdate = $request->validate([
            'feedback' => 'required|array',
            'feedback.*' => 'required|string',
        ]);
        syncFileStorage($submission, $dataToUpdate, 'feedback.*', 'documents/feedbacks' ,'local', 'feedback.show');
        $submission->update(
            array_merge(
                $dataToUpdate,
                [
                    'status' => 'reviewed',
                    'reviewed_by' => $request->user()->id
                ]
            )
        );
        return back()->with('success', "Submission updated successfully");
    }

    public function downloadBulk(Request $request): array
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:submissions,id'
        ]);
        admin_log('download-bulk', self::$name, "Download " . count($validated['ids']) . " submissions");
        return Submission::whereIn('id', $validated['ids'])->pluck('submission')->flatten()->all();
    }

    public function exportBulk(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:submissions,id'
        ]);
        $filename = 'submissions-' . now()->format('Y-m-d') . '.csv';
        admin_log('export-bulk', self::$name, "Exported " . count($validated['ids']) . " submissions");
        return Excel::download(new SubmissionsExport($validated['ids']), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function editBulk(Request $request): RedirectResponse
    {
        $dataToUpdate = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:submissions,id',
            'feedback' => 'required|array',
            'feedback.*' => 'required|string',
        ]);

        // only give new Record for upload the file
        syncFileStorage(new Submission(), $dataToUpdate, 'feedback.*', 'documents/feedbacks' ,'local', 'feedback.show');
        Submission::whereIn('id', $dataToUpdate['ids'])
            ->update([
                'feedback' => $dataToUpdate['feedback'],
                'status' => 'reviewed',
                'reviewed_by' => $request->user()->id
            ]);
            
        admin_log('export-edit', self::$name, "Edited " . count($dataToUpdate['ids']) . " submissions");
        return back()->with('success',  count($dataToUpdate['ids']) . " Submissions updated successfully");
    }

    public function showFeedback(string $path): StreamedResponse
    {
        return streamPrivateFile(
            Submission::class,
            'feedback.show',
            $path,
            'feedback',
            function (User $user, Submission $record) {
                return $user->can('submissions') || $record->team->members()->where('user_id', $user->id)->exists();
            },
            isJsonColumn: true
        );
    }
}
