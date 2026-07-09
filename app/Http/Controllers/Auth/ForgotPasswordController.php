<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Response;


class ForgotPasswordController extends Controller
{
    private array $translation;

    public function __construct()
    {
        $this->translation = app('translations')["auth"];
    }

    /**
     * Show the password reset view to the given user.
     * 
     * @return \Inertia\Response
     */
    public function showForgotPassword(): Response
    {
        return inertia('Auth/Login', [
            'forgotPassword' => true
        ]);
    }


    /**
     * Send a reset link to the given user.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     */
    public function forgotPassword(Request $request): RedirectResponse|null
    {
        $email = $request->validate([
            'email' => 'required|email'
        ], [
            'email.required' => $this->translation['email.required'],
            'email.email' => $this->translation['email.email'],
        ]);

        try {
            $status = Password::sendResetLink($email);
        } catch (\Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
        if (Password::RESET_LINK_SENT === $status) {
            $redirectUrl = $request->input('redirect_url');
            $isPathname = Str::startsWith($redirectUrl, '/') && !Str::startsWith($redirectUrl, '//');
            if($isPathname) {
                $request->session()->put('redirect_url', $request->input('redirect_url'));
            }
            return null;
        }

        return back()->withErrors([
            'email' => $this->translation[$status],
        ]);
    }

    /**
     * Display the password reset view for the given token.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $token
     * @return \Inertia\Response
     */
    public function showResetPasswordForm(Request $request, string $token): Response
    {
        return inertia('Auth/ResetPassword', [
            'email' => $request->email,
            'token' => $token,
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ], [
            'token.required' => $this->translation['token.required'],
            'email.required' => $this->translation['email.required'],
            'email.email' => $this->translation['email.email'],
            'password.required' => $this->translation['password.required'],
            'password.min' => $this->translation['password.min'],
            'password.confirmed' => $this->translation['password.confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password
                ])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if (Password::PASSWORD_RESET === $status) {
            Auth::login(User::where('email', $request->email)->first());
            $redirectUrl = $request->session()->get('redirect_url');
            $request->session()->forget('redirect_url');
            // redirect to login so it automatically logs in
            return go_to('login', [
                'redirect_url' => $redirectUrl
            ]);
        } else {
            return back()->withErrors([
                'general' => $this->translation['token.invalid'],
            ]);
        }
    }
}
