<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Display the registration view.
     * 
     * @return \Inertia\Response
     */
    public function showRegistrationForm(): Response
    {
        return inertia('Auth/Register', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request): RedirectResponse
    {
        $translation = app('translations')["auth"];
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed'
        ], [
            'name.required' => $translation['name.required'],
            'name.string' => $translation['name.string'],
            'email.required' => $translation['email.required'],
            'email.email' => $translation['email.email'],
            'password.required' => $translation['password.required'],
            'password.string' => $translation['password.string'],
            'password.min' => $translation['password.min'],
            'password.confirmed' => $translation['password.confirmed'],
        ]);

        if (!checkTurnstile($request->string("turnstileToken"))) {
            return back()->withErrors([
                'turnstileToken' => $translation['turnstile_token.invalid'],
            ]);
        }

        if (User::where('email', $request->email)->exists()) {
            return back()->withErrors([
                'email' => $translation['email.unique'],
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password
        ]);

        try {
            $redirectUrl = $request->input('redirect_url');
            $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
            $user->sendEmailVerificationNotification($isPathname ? $redirectUrl : null);
        } catch (\Throwable $th) {
            return back()->withErrors(['general' => $th->getMessage()]);
        }

        Auth::login($user);
        return go_to('verification.notice', [
            'redirect_url' => $request->input('redirect_url')
        ]);
    }

    /**
     * Display the verification notice view.
     * 
     * @return \Inertia\Response
     */
    public function needVerification(): Response
    {
        return inertia('Auth/VerificationNotice');
    }

    /**
     * Handle email verification.
     * 
     * @param  string  $id
     * @param  string  $hash
     * @return \Illuminate\Http\RedirectResponse|\Inertia\Response
     */
    public function verify(string $id, string $hash): RedirectResponse|Response
    {
        $user = User::find($id);

        // Check if the user exists and the hash matches
        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return inertia('Auth/VerificationNotice', ['fail' => true]);
        }

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Log the user in
        Auth::login($user);

        $redirectUrl = request()->input('redirect_url');
        $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
        if ($isPathname) {
            return redirect($redirectUrl);
        }
        // Redirect to the dashboard
        return go_to('user.dashboard.index');
    }

    /**
     * Handle email verification resend.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function resend(Request $request): RedirectResponse
    {
        $redirectUrl = $request->input('redirect_url');
        $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
        try {
            $request->user()->sendEmailVerificationNotification($isPathname ? $redirectUrl : null);
        } catch (\Throwable $th) {
            return back()->withErrors(['general' => $th->getMessage()]);
        }
        return back();
    }
}
