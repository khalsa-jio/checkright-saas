<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | uses Laravel's built-in password reset functionality to send secure
    | password reset links to users via email.
    |
    */

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Send a reset link to the given user.
     */
    public function sendResetLinkEmail(Request $request)
    {
        // Validate the email field
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput($request->only('email'));
        }

        // Check for social auth only users
        $socialAuthValidation = $this->validateEmailForPasswordReset($request);
        if ($socialAuthValidation) {
            return $socialAuthValidation;
        }

        // Log the password reset attempt
        Log::info('Password reset requested', [
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $response = $this->broker()->sendResetLink(
            $this->credentials($request)
        );

        if ($response == Password::RESET_LINK_SENT) {
            Log::info('Password reset link sent successfully', [
                'email' => $request->email,
                'response' => $response,
            ]);

            return back()->with('status', trans($response));
        }

        // Log failed attempts for security monitoring
        Log::warning('Password reset link failed to send', [
            'email' => $request->email,
            'response' => $response,
            'ip_address' => $request->ip(),
        ]);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => trans($response)]);
    }

    /**
     * Get the needed authentication credentials from the request.
     */
    protected function credentials(Request $request): array
    {
        return $request->only('email');
    }

    /**
     * Get the broker to be used during password reset.
     */
    public function broker()
    {
        return Password::broker();
    }

    /**
     * Validate the email for the given request.
     */
    protected function validateEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);
    }

    /**
     * Validate if user can reset password (check for OAuth-only users).
     */
    protected function validateEmailForPasswordReset(Request $request)
    {
        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user && $user->password === null && $user->socialAccounts()->exists()) {
            // Handle OAuth-only users differently
            $providers = $user->socialAccounts()->pluck('provider')->map(function ($provider) {
                return ucfirst($provider);
            })->toArray();

            Log::warning('OAuth-only user attempted password reset', [
                'email' => $request->email,
                'user_id' => $user->id,
                'social_providers' => $providers,
                'ip_address' => $request->ip(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'This account uses social login (' . implode(', ', $providers) .
                              '). Please sign in using your social account, or contact support to add a password to your account.',
                ]);
        }

        // Continue with normal flow
    }
}
