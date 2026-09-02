<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LegacyPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_DECAY_SECONDS = 300;

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login', [
            'hasUsers' => User::query()->count() > 0,
        ]);
    }

    public function login(Request $request, LegacyPasswordService $passwords, ActivityLogger $logger): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        $login = mb_strtolower(trim($credentials['login']));
        $throttleKey = $this->throttleKey($login, (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()
                ->withErrors(['login' => "Muitas tentativas de acesso. Tente novamente em {$seconds} segundo(s)."])
                ->onlyInput('login');
        }

        $user = User::query()
            ->where(function ($query) use ($login) {
                $query->where('username', $login)->orWhere('email', $login);
            })
            ->first();

        if (!$user || !$user->isActive() || !$passwords->verifyAndUpgrade($user, $credentials['password'])) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);
            return back()
                ->withErrors(['login' => 'Usuário/e-mail ou senha inválidos.'])
                ->onlyInput('login');
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, false);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'remember_token' => null,
        ])->save();

        $logger->log('Login realizado');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, ActivityLogger $logger): RedirectResponse
    {
        if (Auth::check()) {
            $logger->log('Logout realizado');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(string $login, string $ip): string
    {
        return 'login:'.Str::lower(Str::transliterate($login)).'|'.$ip;
    }
}
