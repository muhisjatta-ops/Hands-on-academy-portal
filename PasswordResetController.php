<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController
{
    public function sendLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($data);

        SecurityLog::record('password.reset_requested', null, $data['email'], [
            'status' => $status,
        ]);

        // Always the same response, whether or not the address exists.
        // Otherwise this endpoint becomes a way to enumerate staff emails.
        return response()->json([
            'message' => 'If that address is registered, a reset link is on its way.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => [
                'required', 'confirmed',
                PasswordRule::min(10)->letters()->numbers()->uncompromised(),
                // uncompromised() checks the k-anonymity HaveIBeenPwned API.
                // Worth it: staff reuse passwords everywhere.
            ],
        ]);

        $status = Password::reset($data, function (User $user, string $password) use ($request) {
            $user->forceFill([
                'password'              => $password,
                'remember_token'        => Str::random(60),
                'password_changed_at'   => now(),
                'must_change_password'  => false,
                'failed_login_attempts' => 0,
                'locked_until'          => null,
            ])->save();

            // A reset means "I may have been compromised" — kill every
            // existing session for this account.
            DB::table('sessions')->where('user_id', $user->id)->delete();

            SecurityLog::record('password.reset_completed', $user);

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This reset link is invalid or has expired.',
            ]);
        }

        return response()->json(['message' => 'Password updated. You can sign in now.']);
    }
}
