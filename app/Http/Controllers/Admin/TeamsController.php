<?php

namespace App\Http\Controllers\Admin;

use App\Exports\TeamsExport;
use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeamsController extends Controller
{
    public static string $name = 'teams';
    public static string $icon = "Users";
    public static int $position = 2;

    private function getPaginatedData(Request $request): array
    {
        return
            getPaginatedData(
                $request,
                Team::class,
                ['id', 'name', 'invite_code', 'leader.name', 'competition.name', 'members.name'],
                'admin.teams.index',
            );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/Teams', $this->getPaginatedData($request));
    }

    public function show(Request $request, Team $team): Response
    {
        $team->load('leader', 'competition', 'members');

        return inertia('Admin/Teams', array_merge($this->getPaginatedData($request), [
            'showData' => [
                'id' => $team->id,
                'name' => $team->name,
                'invite_code' => $team->invite_code,
                'leader_name' => $team->leader->name ?? null,
                'competition_name' => $team->competition->name ?? null,
                'members_name' => $team->members->pluck('name', 'id'),
            ],
        ]));
    }

    public function destroy(Team $team): RedirectResponse
    {
        $team->delete();
        return back()->with("success", "Team $team->name deleted successfully");
    }

    public function restore(Team $team): RedirectResponse
    {
        $team->restore();
        return back()->with("success", "Team $team->name restore successfully");
    }

    public function deleteBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:teams,id'
        ]);
        Team::whereIn('id', $validated['ids'])
            ->get()
            ->each(fn($team) => $team->delete());
        return back()->with("success", "Teams deleted successfully");
    }

    public function restoreBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:teams,id'
        ]);
        Team::onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->get()
            ->each(fn($team) => $team->restore());
        return back()->with("success", "Teams restored successfully");
    }

    public function exportBulk(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:teams,id'
        ]);
        $filename = 'teams-' . now()->format('Y-m-d') . '.csv';
        admin_log('export-bulk', self::$name, "Exported " . count($validated['ids']) . " teams");
        return Excel::download(new TeamsExport($validated['ids']), $filename, \Maatwebsite\Excel\Excel::CSV);
    }
}
