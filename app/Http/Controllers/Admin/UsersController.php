<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\FlexNotify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UsersController extends Controller
{
    public static string $name = 'users';
    public static string $icon = "User";

    private $fillableColumns = ['name', 'email', 'avatar', 'password', 'privileges'];

    private function getPaginatedData(Request $request): array
    {
        return
            array_merge(
                getPaginatedData(
                    $request,
                    User::class,
                    ['id', 'avatar' ,'name', 'email', 'privileges', 'created_at'],
                    'admin.users.index',
                ),
                ['privileges' => getAdminResources()],
            );
    }

    public function index(Request $request): Response
    {
        return inertia('Admin/Users', $this->getPaginatedData($request));
    }

    public function show(Request $request, User $user): Response
    {
        return inertia('Admin/Users', array_merge($this->getPaginatedData($request), [
            'showData' => $this->getUserDetails($user),
        ]));
    }

    public function edit(Request $request, User $user): Response
    {
        return inertia('Admin/Users', array_merge($this->getPaginatedData($request), [
            'editData' => $user->only('id', 'privileges'),
        ]));
    }

    public function create(Request $request): Response
    {
        return inertia('Admin/Users', array_merge($this->getPaginatedData($request), [
            'createData' => array_fill_keys($this->fillableColumns, ''),
        ]));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // only to update the admin privillage
        $user->update($request->validate(["privileges" => 'nullable|array']));
        return back()->with('success', "User updated successfully");
    }

    public function store(Request $request): RedirectResponse
    {
        User::create($this->validateAndStoreFile($request, new User()))
            ->markEmailAsVerified();
        return back()->with('success', "User created successfully");
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();
        admin_log('delete', self::$name, "Name: {$user->name} Email: {$user->email}");
        return back()->with("success", "User deleted successfully");
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restore();
        admin_log('restore', self::$name, "Name: {$user->name} Email: {$user->email}");
        return back()->with("success", "User restore successfully");
    }

    public function deleteBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:users,id'
        ]);
        User::whereIn('id', $validated['ids'])->delete();
        admin_log('delete-bulk', self::$name, "Deleted " . count($validated['ids']) . " users");
        return back()->with("success", "Users deleted successfully");
    }

    public function restoreBulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:users,id'
        ]);
        User::whereIn('id', $validated['ids'])->restore();
        admin_log('restore-bulk', self::$name, "Restored " . count($validated['ids']) . " users");
        return back()->with("success", "Users restore successfully");
    }

    public function exportBulk(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:users,id'
        ]);
        $filename = 'users-' . now()->format('Y-m-d') . '.csv';
        admin_log('export-bulk', self::$name, "Exported " . count($validated['ids']) . " users");
        return Excel::download(new UsersExport($validated['ids']), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function sendAnnouncement(Request $request): RedirectResponse
    {
        $rules = [
            'ids'   => 'required|array',
            'ids.*' => 'required|integer|exists:users,id',
            'headline' => 'required|array',
            'message' => 'required|array',
            'sendMail' => 'required|boolean',
        ];

        $customAttributes = [
            'headline' => 'Headline',
            'message' => 'Message',
        ];

        foreach (app('supportedLocales')['active'] as $locale) {
            // Add the validation rule for the current locale
            $rules["headline.$locale"] = 'required|string';
            $rules["message.$locale"] = 'required|string';

            // Add the corresponding custom attribute name for that locale
            $customAttributes["headline.$locale"] = "Headline ($locale)";
            $customAttributes["message.$locale"] = "Message ($locale)";
        }

        $validated = $request->validate($rules, [], $customAttributes);

        $users = User::whereIn('id', $validated['ids'])->get();
        Notification::send($users, new FlexNotify('adminGenerated', $validated['headline'], $validated['message'], $validated['sendMail']));
        admin_log('send-announcement', self::$name, "Sent announcement to " . count($validated['ids']) . " users");
        return back()->with("success", "Announcement sent successfully");
    }

    private function getUserDetails(User $user): array
    {
        $user->load('teams', 'profile');
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'privileges' => $user->privileges,
            'created_at' => $user->created_at,
            'teams' => $user->teams->pluck('name', 'id') ?? null,
            'birth_date' => $user->profile->birth_date ?? null,
            'phone_number' => $user->profile->phone_number ?? null,
            'province' => $user->profile->province ?? null,
            'address' => $user->profile->address ?? null,
            'institution' => $user->profile->institution ?? null,
            'student_id' => $user->profile->student_id ?? null,
            'institution_card' => $user->profile->institution_card ?? null,
            'follow_proof' => $user->profile->follow_proof ?? null,
            'twibbon_proof' => $user->profile->twibbon_proof ?? null
        ];
    }

    private function validateAndStoreFile(Request $request, User $user): array
    {
        $request->validate([
            "privileges" => 'nullable|array',
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'avatar' => 'nullable|string',
        ]);

        $dataToUpdate = $request->only($this->fillableColumns);
        $request->has('avatar') && syncFileStorage($user, $dataToUpdate, 'avatar', 'images/avatars');
        return $dataToUpdate;
    }
}
