<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\GeneralInfo;
use App\Models\Registration;
use App\Models\Submission;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamsController extends Controller
{
    public static string $name = 'teams';

    public static int $position = 1;

    public static string $icon = 'Users';

    private array $translation;

    public function __construct()
    {
        $this->translation = app('translations')['user-teams'];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        return inertia('User/Teams', [
            'profileEmpty' => empty(Cache::rememberForever("user-profile-{$user->id}", fn () => $user->profile)),
            'teams' => DashboardController::teamsData($request->user()),
            'competitions' => Competition::with('teams')
                ->get()
                ->map(
                    fn ($competition) => [
                        'id' => $competition->id,
                        'name' => $competition->name,
                        'description' => $competition->description[app()->getLocale()] ?? '',
                        'registrationStatus' => getRegistrationStatus($competition->timeline),
                        'url' => to('competition', ['name' => $competition->name]),
                        'teams' => $competition->teams->map(function ($team) {
                            return [
                                'id' => $team->id,
                                'name' => $team->name,
                                'members' => $team->members->map(fn ($member) => $member->only(['id', 'avatar']))->all(),
                            ];
                        }),
                    ]
                ),
        ]);
    }

    public function showDetailsTeam(Request $request, string $public_id): Response
    {
        // Get the team and check if user is one of the members
        $team = Team::where('public_id', $public_id)
            ->whereHas('members', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['members', 'competition', 'registrations'])
            ->firstOrFail();
        
        $now = Carbon::now();
        $activePeriod = collect($team->competition->timeline)
            ->first(function ($event) use ($now) {
                return ! empty($event['is_registration']) && $now->between(
                    Carbon::parse($event['start']),
                    Carbon::parse($event['end'])
                );
            });

        return inertia('User/TeamView', [
            'team' => [
                'name' => $team->name,
                'public_id' => $public_id,
                'invite_code' => $team->invite_code,
                'members' => $team->members->map(function ($member) {
                    $memberData = $member->only(['id', 'avatar', 'name']);
                    $memberData['profileEmpty'] = empty(Cache::rememberForever("user-profile-{$member->id}", fn () => $member->profile));

                    return $memberData;
                })->all(),
                'bans' => $team->bans->map(fn ($member) => $member->only(['id', 'avatar', 'name']))->all(),
                'leader_id' => $team->leader_id,
                'competition' => [
                    'name' => $team->competition->name,
                    'url' => to('competition', ['name' => $team->competition->name]),
                    'min_team_size' => $team->competition->min_team_size,
                    'price' => $team->competition->price,
                ],
                'registration_end' => $activePeriod ? Carbon::parse($activePeriod['end'])->toIso8601ZuluString() : null,
                'registrationStatus' => getregistrationStatus($team->competition->timeline),
                'registrations' => $team->registrations->map(fn ($registration) => $registration->only(['id', 'status', 'payment_proof', 'submitted_at', 'notes', 'group_link']))->all(),
            ],
            'paymentMethods' => Cache::rememberForever('general_info', fn () => GeneralInfo::first())->payment_methods,
        ]);
    }

    public function showSubmissionsTeam(Request $request, string $public_id): Response
    {
        // Get the team and check if user is one of the members
        $team = Team::where('public_id', $public_id)
            ->whereHas('members', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['competition', 'submissions'])
            ->firstOrFail();

        $now = Carbon::now();

        $activePeriod = null;
        $startNextPeriod = null;

        foreach ($team->competition->timeline as $event) {
            // Skip non-submission events
            if (! $event['is_submission']) {
                continue;
            }

            $start = Carbon::parse($event['start']);
            $end = Carbon::parse($event['end']);

            if ($now->between($start, $end)) {
                $activePeriod = $event;

                // Skip the current event since active period will not be the nextperiod
                continue;
            }

            if ($start->isFuture()) {
                if (is_null($startNextPeriod) || $start->lt($startNextPeriod)) {
                    $startNextPeriod = $start;
                }
            }
        }
        $canSubmit = $activePeriod && $team->registrations
            ->where('status', 'approved')
            ->first() &&
            $team->submissions
                ->whereBetween('submitted_at', [
                    Carbon::parse($activePeriod['start']),
                    Carbon::parse($activePeriod['end']),
                ])
                ->isEmpty();

        $SubmissionStatus = [
            'canSubmit' => $canSubmit,
            'closesAt' => $canSubmit ? Carbon::parse($activePeriod['end'])->toIso8601ZuluString() : null,
            'nextOpenAt' => $startNextPeriod ? $startNextPeriod->toIso8601ZuluString() : null,
        ];

        return inertia('User/TeamView', [
            'team' => [
                'name' => $team->name,
                'public_id' => $public_id,
                'competition' => $team->competition->only('name'),
                'registrations' => $team->registrations->map(fn ($registration) => $registration->only(['id', 'status', 'payment_proof', 'submitted_at', 'notes']))->all(),
                'submissions' => $team->submissions->map(fn ($submission) => $submission->only(['id', 'status', 'submission', 'submitted_at', 'feedback']))->all(),
                'SubmissionStatus' => $SubmissionStatus,
            ],
        ]);
    }

    public function showJoinPopup(Request $request, string $invite_code): Response
    {
        $user = $request->user();
        $team = Team::where('invite_code', $invite_code)
            ->with(['members', 'competition'])
            ->firstOrFail();

        return inertia('User/JoinPopup', [
            'profileEmpty' => empty(Cache::rememberForever("user-profile-{$user->id}", fn () => $user->profile)),
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'invite_code' => $invite_code,
                'members' => $team->members->map(fn ($member) => $member->only(['id', 'avatar', 'name']))->all(),
                'leader_id' => $team->leader_id,
                'competition' => $team->competition->only('name'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Make sure user has completed profile
        if (empty(Cache::rememberForever("user-profile-{$user->id}", fn () => $user->profile))) {
            return back()->with('error', $this->translation['please-complete-profile'] ?? 'Please complete your profile first.');
        }

        $request->validate([
            'name' => 'required|string',
            'competition' => 'required|integer|exists:competitions,id',
        ]);

        // Make sure user can only be leader of one team
        $isAlreadyLeader = Team::where('leader_id', $user->id)
            ->exists();

        if ($isAlreadyLeader) {
            return back()->with('error', $this->translation['already-leading-team'] ?? 'You are already leading a team.');
        }

        // Make sure registration has not closed
        $competition = Competition::findOrFail($request->competition);
        if (getRegistrationStatus($competition->timeline) === 'closed') {
            return back()->with('error', $this->translation['registration-closed-for-this-competition'] ?? 'Registration for this competition has closed.');
        }

        $team = $user->teams()->create([
            'name' => $request->name,
            'competition_id' => $request->competition,
            'leader_id' => $user->id,
        ]);

        Cache::forget("user-{$user->id}-dashboard");

        return go_to('user.teams.show', ['public_id' => $team->public_id]);
    }

    public function join(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Make sure user has completed profile
        if (empty(Cache::rememberForever("user-profile-{$user->id}", fn () => $user->profile))) {
            return back()->with('error', $this->translation['please-complete-profile'] ?? 'Please complete your profile first.');
        }

        $request->validate([
            'invite_code' => 'required|string|exists:teams,invite_code',
        ]);

        $team = Team::with(['members', 'bans', 'competition', 'registrations'])
            ->where('invite_code', $request->invite_code)
            ->first();

        // Make sure user is not already a member
        if ($team->members->contains($user->id)) {
            return back()->with('error', $this->translation['already-member-of-this-team'] ?? 'You are already a member of this team.');
        }

        // Make sure user is not banned
        if ($team->bans->contains($user->id)) {
            return back()->with('error', $this->translation['you-are-banned'] ?? 'You are banned to join this team.');
        }

        $competition = $team->competition;

        // Make sure team is not full
        if ($team->members->count() >= $competition->max_team_size) {
            return back()->with('error', $this->translation['team-is-full'] ?? 'This team is full.');
        }

        // Make sure registration has not closed
        if (getRegistrationStatus($competition->timeline) === 'closed') {
            return back()->with('error', $this->translation['registration-closed-for-this-competition'] ?? 'Registration for this competition has closed.');
        }

        // Make sure the team does not have an active registration
        $activeRegistration = $team->registrations
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($activeRegistration) {
            return back()->with('error', $this->translation['already-have-active-registration'] ?? 'This team already have an active registration.');
        }

        $isAlreadyInStrictCompetition = $user->teams()
            ->whereHas('competition', function ($query) {
                $query->where('enforce_single_team_rule', true);
            })->exists();

        // Check if the user is already in a strict competition
        if ($isAlreadyInStrictCompetition) {
            return back()->with('error', $this->translation['already-in-strict-competition'] ?? 'You are already in a single-team competition and cannot join another.');
        }

        // Check if the team user WANTS TO JOIN is strict.
        if ($competition->enforce_single_team_rule && $user->teams->isNotEmpty()) {
            return back()->with('error', $this->translation['already-have-team-and-try-join-strict-competition'] ?? 'This is a single-team competition. Ask your team leader to ban you if you want to join this team.');
        }

        // For regular competitions
        if (! $competition->enforce_single_team_rule) {
            // Make sure user has not joined more than 2 teams
            if ($user->teams->count() >= 2) {
                return back()->with('error', $this->translation['cannot-join-more-than-2-teams'] ?? 'You cannot join more than 2 teams in total.');
            }
            // Make sure user is not already in this competition
            if ($user->teams->contains('competition_id', $competition->id)) {
                return back()->with('error', $this->translation['already-participating'] ?? 'You are already participating in this competition.');
            }
        }

        $team->members()->attach($user->id);

        // refresh the object team to include the new member and forget the cache
        $team->refresh();
        foreach ($team->members as $member) {
            Cache::forget("user-{$member->id}-dashboard");
        }

        return go_to('user.teams.show', ['public_id' => $team->public_id]);
    }

    public function ban(Request $request, string $public_id, string $user_id): RedirectResponse
    {
        $team = Team::with('registrations')
            ->where('public_id', $public_id)
            ->firstOrFail();

        // Make sure user is the leader
        if ($team->leader_id !== $request->user()->id) {
            return back()->with('error', $this->translation['not-the-leader'] ?? 'You are not the leader of this team.');
        }

        if ($user_id === $team->leader_id) {
            return back()->with('error', $this->translation['cannot-ban-leader'] ?? 'You cannot ban the leader of the team.');
        }

        $activeRegistration = $team->registrations
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($activeRegistration) {
            return back()->with('error', $this->translation['already-have-active-registration'] ?? 'This team already have an active registration.');
        }

        // move the deleted member to the banned table
        $team->members()->detach($user_id);
        $team->bans()->attach($user_id);

        $team->refresh();
        foreach ($team->members as $member) {
            Cache::forget("user-{$member->id}-dashboard");
        }

        return back()->with('success', $this->translation['member-banned-successfully'] ?? 'Member banned successfully');
    }

    public function unban(Request $request, string $public_id, string $user_id): RedirectResponse
    {
        $team = Team::where('public_id', $public_id)
            ->firstOrFail();

        // Make sure user is the leader
        if ($team->leader_id !== $request->user()->id) {
            return back()->with('error', $this->translation['not-the-leader'] ?? 'You are not the leader of this team.');
        }

        // remove the deleted member from the banned table
        $team->bans()->detach($user_id);

        $team->refresh();
        foreach ($team->members as $member) {
            Cache::forget("user-{$member->id}-dashboard");
        }

        return back()->with('success', $this->translation['member-unbanned-successfully'] ?? 'Member unbanned successfully');
    }

    public function register(Request $request, string $public_id): RedirectResponse
    {
        $team = Team::with(['registrations', 'members', 'competition'])
            ->where('public_id', $public_id)
            ->firstOrFail();

        // Make sure user is a member of the team
        if (! $team->members->contains($request->user()->id)) {
            return back()->with('error', $this->translation['not-a-member-of-this-team'] ?? 'You are not a member of this team');
        }

        // Make sure the team does not have an active registration
        $activeRegistration = $team->registrations
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($activeRegistration) {
            return back()->with('error', $this->translation['already-have-active-registration'] ?? 'This team already have an active registration.');
        }

        // Make sure registration has not closed
        if (getRegistrationStatus($team->competition->timeline) === 'closed') {
            return back()->with('error', $this->translation['registration-closed-for-this-competition'] ?? 'Registration for this competition has closed.');
        }

        // Make sure every members have profile completed
        foreach ($team->members as $member) {
            if (empty(Cache::rememberForever("user-profile-{$member->id}", fn () => $member->profile))) {
                return back()->with('error', $this->translation['member-profile-not-completed'] ?? 'The members need to complete their profile first');
            }
        }

        $team_count = $team->members->count();
        // Make sure the team at least get minimum number of members
        if ($team_count < $team->competition->min_team_size) {
            return back()->with('error', str_replace(':min_team_size', $team->competition->min_team_size, $this->translation['team-too-small'] ?? 'The team needs to have at least :min_team_size members.'));
        }

        // Make sure the team at most get maximum number of members
        if ($team_count > $team->competition->max_team_size) {
            return back()->with('error', str_replace(':max_team_size', $team->competition->max_team_size, $this->translation['team-too-big'] ?? 'The team needs to have at least :min_team_size members.'));
        }

        $dataToUpdate = $request->validate([
            'payment_proof' => 'required|string',
        ]);

        $registration = $team->registrations()->make();
        syncFileStorage($registration, $dataToUpdate, 'payment_proof', 'images/payment_proofs', 'local', 'payment-proof.show');
        $registration->fill([
            ...$dataToUpdate,
            'competition_id' => $team->competition_id,
            'price_at_registration' => $team->competition->price,
        ]);
        $registration->save();

        return back()->with('success', $this->translation['registration-submitted-successfully'] ?? 'Registration submitted successfully');
    }

    public function showPaymentProof(string $path): StreamedResponse
    {
        return streamPrivateFile(
            Registration::class,
            'payment-proof.show',
            $path,
            'payment_proof',
            function (User $user, Registration $record) {
                return $user->can('registrations') || $record->team->members()->where('user_id', $user->id)->exists();
            }
        );
    }

    public function submission(Request $request, string $public_id): RedirectResponse
    {
        $team = Team::with(['competition', 'submissions', 'registrations', 'members'])
            ->where('public_id', $public_id)
            ->firstOrFail();

        // Make sure the team registered
        if ($team->registrations->contains('status', 'pending')) {
            return back()->with('error', $this->translation['team-not-registered'] ?? 'Team not registered');
        }

        // Make sure the user is a member of the team
        if (! $team->members->contains($request->user()->id)) {
            return back()->with('error', $this->translation['not-a-member-of-this-team'] ?? 'You are not a member of this team');
        }

        // Make sure the submission has not closed
        $now = Carbon::now();
        $activePeriod = collect($team->competition->timeline)
            ->first(function ($event) use ($now) {
                return ! empty($event['is_submission']) && $now->between(
                    Carbon::parse($event['start']),
                    Carbon::parse($event['end'])
                );
            });

        if (! $activePeriod) {
            return back()->with('error', $this->translation['submission-closed-message'] ?? 'Submission for this competition has been closed.');
        }

        // Make sure the team has not submitted yet
        $hasExistingSubmission = $team->submissions
            ->whereBetween('submitted_at', [
                Carbon::parse($activePeriod['start']),
                Carbon::parse($activePeriod['end']),
            ])
            ->isNotEmpty();

        if ($hasExistingSubmission) {
            return back()->with('error', $this->translation['already-have-submission'] ?? 'Your team already have a submission for this period.');
        }

        $dataToUpdate = $request->validate([
            'submission' => 'required|array',
            'submission.*' => 'required|string',
        ]);

        $submission = $team->submissions()->make();
        syncFileStorage($submission, $dataToUpdate, 'submission.*', 'documents/submissions', 'local', 'submission.show');
        $submission->fill([
            ...$dataToUpdate,
            'competition_id' => $team->competition_id,
        ]);
        $submission->save();

        return back()->with('success', $this->translation['submission-submitted-successfully'] ?? 'Submission submitted successfully');
    }

    public function showSubmission(string $path): StreamedResponse
    {
        return streamPrivateFile(
            Submission::class,
            'submission.show',
            $path,
            'submission',
            function (User $user, Submission $record) {
                return $user->can('submissions') || $record->team->members()->where('user_id', $user->id)->exists();
            },
            isJsonColumn: true
        );
    }
}
