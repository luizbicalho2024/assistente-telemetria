<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LegacyPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
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
        $user = User::query()
            ->where(function ($query) use ($login) {
                $query->where('username', $login)->orWhere('email', $login);
            })
            ->first();

        if (!$user || !$user->isActive() || !$passwords->verifyAndUpgrade($user, $credentials['password'])) {
            return back()->withErrors(['login' => 'Usuário/e-mail ou senha inválidos.'])->onlyInput('login');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $logger->log('Login realizado');

        return redirect()->intended(route('dashboard'));
    }

    public function bootstrap(Request $request, ActivityLogger $logger): RedirectResponse
    {
        abort_if(User::query()->count() > 0, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'min:3', 'max:80'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            'name' => trim($data['name']),
            'username' => mb_strtolower(trim($data['username'])),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => 'admin',
            'active' => true,
            'disabled' => false,
        ]);

        Auth::login($user, true);
        $request->session()->regenerate();
        $logger->log('Primeiro administrador criado');

        return redirect()->route('dashboard');
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
}
