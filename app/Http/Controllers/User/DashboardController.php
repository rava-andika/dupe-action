<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;

class DashboardController extends Controller
{
    public static string $name = 'dashboard';
    public static int $position = 0;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = Cache::rememberForever("user-profile-{$user->id}", fn() => $user->profile);
        $teamsData = $this->teamsData($user);

        return inertia('User/Dashboard', [
            'profileEmpty' => empty($profile),
            'teams' => $teamsData
        ]);
    }

    public static function teamsData(User $user): array
    {
        $teams = Cache::rememberForever("user-{$user->id}-dashboard", function () use ($user) {
            return $user->teams()
                ->withTrashed()
                ->with(['members', 'competition' => fn($query) => $query->withTrashed()])
                ->get();
        });

        return $teams->map(function ($team) {
            [$currentPhase, $deadline] = self::getPhaseAndDeadline($team->competition->timeline);
            return [
                'id' => $team->id,
                'public_id' => $team->public_id,
                'name' => $team->name,
                'members' => $team->members->map(fn($member) => $member->only(['id', 'avatar']))->all(),
                'deleted_at' => $team->deleted_at,
                'competition' => [
                    'name' => $team->competition->name,
                    'url' => to('competition', ['name' => $team->competition->name]),
                    'phase' => $currentPhase,
                    'deadline' => $deadline,
                    'deleted_at' => $team->competition->deleted_at,
                ],
            ];
        })->toArray();
    }

    private static function getPhaseAndDeadline(array $timeline): array
    {
        $currentPhase = null;
        $deadline = null;
        $today = Carbon::now();

        foreach ($timeline as $event) {
            $start = Carbon::parse($event['start']);
            $end = Carbon::parse($event['end']);

            // Get the current phase
            if (is_null($currentPhase) && $today->between($start, $end)) {
                $currentPhase = $event['description'];
            }

            // check if the event is a submission and is in the future
            if ($event['is_submission'] && $end->isFuture()) {
                // check if the deadline is null or the end date is earlier than the current deadline
                if (is_null($deadline) || $end->lt($deadline)) {
                    $deadline = $end;
                }
            }
        }

        return [$currentPhase ?? getregistrationStatus($timeline), $deadline?->toIso8601ZuluString()];
    }
}
