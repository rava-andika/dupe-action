<?php

namespace App\Http\Controllers;

use App\Models\GeneralInfo;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public static string $name = 'settings';

    public static string $icon = 'Settings';

    public static int $position = 999;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return inertia('Settings', [
            'profile' => Cache::rememberForever("user-profile-{$user->id}", fn () => $user->profile),
            'generalInfo' => Cache::rememberForever('general_info', fn () => GeneralInfo::first()),
        ]);
    }

    public function updateGeneralProfile(Request $request): RedirectResponse
    {
        $translation = app('translations')['auth'];

        $dataToUpdate = $request->validate([
            'name' => 'required|string',
            'avatar' => 'nullable|string',
            'email' => 'required|email|unique:users,email,'.$request->user()->id,
        ], [
            'name.required' => $translation['name.required'],
            'name.string' => $translation['name.string'],
            'email.required' => $translation['email.required'],
            'email.email' => $translation['email.email'],
        ]);

        $user = $request->user();
        $request->has('avatar') && syncFileStorage($user, $dataToUpdate, 'avatar', 'images/avatars');
        $user->update($dataToUpdate);

        return back()->with('success', 'Profile updated successfully');
    }

    public function updateDetailsProfile(Request $request): RedirectResponse
    {
        $dataToUpdate = $request->validate([
            'birth_date' => 'required|date|before:today',
            'phone_number' => 'required|numeric',
            'address' => 'required|string',
            'province' => 'required|string',
            'institution' => 'required|string',
            'student_id' => 'required|string',
            'institution_card' => 'required|string',
            'follow_proof' => 'required|array|max:'.count(Cache::rememberForever('general_info', fn () => GeneralInfo::first())->social_media_to_follow ?? []),
            'follow_proof.*' => 'required|string',
            'twibbon_proof' => 'required|string',
        ]);

        $user = $request->user();
        $profile = $user->profile()->firstOrNew();

        // Save the uploaded files
        syncFileStorage($profile, $dataToUpdate, 'institution_card', 'images/institution_cards', 'local','institution-card.show');
        syncFileStorage($profile, $dataToUpdate, 'follow_proof.*', 'images/follow_proofs', 'local', 'follow-proof.show');
        syncFileStorage($profile, $dataToUpdate, 'twibbon_proof', 'images/twibbon_proofs', 'local', 'twibbon-proof.show');

        $profile->fill($dataToUpdate);
        $profile->save();

        return back()->with('success', 'Profile updated successfully');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $dataToUpdate = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);
        $request->user()->update($dataToUpdate);

        return back()->with('success', 'Password updated successfully');
    }

    public function deleteAccount(Request $request): RedirectResponse
    {
        $validate = $request->validate([
            'password' => 'required|string',
        ]);
        $user = $request->user();

        if (! password_verify($validate['password'], $user->password)) {
            return back()->withErrors([
                'password' => 'The provided password was incorrect.',
            ]);
        }

        try {
            Auth::logout();
            $user->forceDelete();

            return back()->with('success', 'Account deleted successfully.'); 
        } catch (QueryException $e) {
            // Check if the error is a foreign key constraint violation.
            // SQLSTATE 23000 is the standard code for integrity constraint violations.
            if ($e->getCode() === '23000') {
                // If so, log the user back in since the deletion failed
                Auth::login($user);
                return back()->with('error', 'Cannot delete account because you are the leader of a team.');
            }

            // If it was a different database error, re-throw the exception.
            throw $e;
        }

    }

    public function showInstitutionCard(string $path): StreamedResponse
    {
        return streamPrivateFile(
            UserProfile::class,
            'institution-card.show',
            $path,
            'institution_card',
            fn (User $user, UserProfile $record) => $user->can('users') || $user->id === $record->user_id
        );
    }

    public function showFollowProof(string $path): StreamedResponse
    {
        return streamPrivateFile(
            UserProfile::class,
            'follow-proof.show',
            $path,
            'follow_proof',
            fn (User $user, UserProfile $record) => $user->can('users') || $user->id === $record->user_id,
            isJsonColumn: true
        );
    }

    public function showTwibbonProof(string $path): StreamedResponse
    {
        return streamPrivateFile(
            UserProfile::class,
            'twibbon-proof.show',
            $path,
            'twibbon_proof',
            fn (User $user, UserProfile $record) => $user->can('users') || $user->id === $record->user_id
        );
    }
}
