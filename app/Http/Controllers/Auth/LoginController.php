<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Show the login form.
     * 
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm(): Response
    {
        return inertia('Auth/Login');
    }

    /**
     * Handle the login request.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $translation = app('translations')["auth"];

        $credetials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ], [
            'email.required' => $translation['email.required'],
            'email.email' => $translation['email.email'],
            'password.required' => $translation['password.required'],
            'password.string' => $translation['password.string'],
        ]);

        // check if the email is not exists
        $user = User::withTrashed()->where('email', $credetials['email'])->first();
        if (!$user) {
            return back()->withErrors([
                'general' => $translation['passwords.user'],
            ]);
        } elseif ($user->trashed()) {
            // Check if the user is soft-deleted 
            return back()->withErrors([
                'general' => $translation['account-deactivated'],
            ]);
        } elseif (!$user->password && $user->google_id) {
            // check if the user is registered with google and has no password
            // ensuring attacker can't access user acc just by empty password
            return back()->withErrors([
                'general' => $translation['user.google'],
            ]);
        }

        if (Auth::attempt($credetials, $request->boolean('remember'))) {
            // redirect user to redirect link if exists
            $redirectUrl = $request->input('redirect_url');
            $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
            if ($isPathname) {
                return redirect($redirectUrl);
            }

            if ($user->can('dashboard')) {
                return go_to('admin.dashboard.index');
            }
            return go_to('user.dashboard.index');
        }

        return back()->withErrors([
            'general' => $translation['credentials.invalid'],
        ]);
    }

    /**
     * Redirect the user to the Google authentication page.
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $redirectUrl = $request->input('redirect_url');
        $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
        if ($isPathname) {
            $request->session()->put('redirect_url', $request->input('redirect_url'));
        }
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the Google authentication callback.
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        // Find user by email, including soft-deleted ones
        $user = User::withTrashed()->where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Check if the user is soft-deleted
            if ($user->trashed()) {
                // If the account is deactivated, redirect to login with an error.
                return go_to('login')->withErrors([
                    'general' => app('translations')["auth"]['account-deactivated'],
                ]);
            }

            // User exists and is active, so update their info if needed.
            $user->update([
                'google_id' => $user->google_id ?? $googleUser->getId(),
                'avatar' => $user->avatar ?? $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
        } else {
            // No user found, so create a new one.
            $user = User::create([
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);
            event(new Verified($user));
        }

        // Log the user in
        Auth::login($user, true);

        // redirect user to redirect link if exists
        $redirectUrl = session('redirect_url');
        if ($redirectUrl) {
            session()->forget('redirect_url');
            return redirect($redirectUrl);
        }

        if (Auth::user()->can('dashboard')) {
            return go_to('admin.dashboard.index');
        }
        return go_to('user.dashboard.index');
    }

    /**
     * Logout the user.
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();
        return go_to('home');
    }
}
