<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NigtaxProPasswordWebController extends Controller
{
    public function showResetForm(Request $request, string $token): View
    {
        return view('nigtax-pro.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email') ?? $request->email,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = app(\App\Services\NigtaxProSubscriptionService::class)->normalizeEmail((string) $request->email);

        $status = Password::broker('nigtax_pro')->reset(
            [
                'email' => $email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token' => $request->token,
            ],
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $calc = trim((string) config('services.nigtax.calculator_url', ''));
            $target = $calc !== '' ? rtrim($calc, '/') : url('/');

            return redirect()->away($target.'?nigtax_pro_password_reset=1');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
