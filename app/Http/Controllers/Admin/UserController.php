<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name', 'asc')->get(),
            'roles' => collect(array_keys(config('modules.defaults', [])))->merge(Role::query()->pluck('slug'))->unique()->values()->all(),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'username' => ['required','string','min:3','max:80'],
            'email' => ['required','email','max:180'],
            'password' => ['required','string','min:8'],
            'role' => ['required','string','max:80'],
        ]);

        $username = mb_strtolower(trim($data['username']));
        $email = mb_strtolower(trim($data['email']));
        if (User::query()->where('username', $username)->exists() || User::query()->where('email', $email)->exists()) {
            return back()->withErrors(['username' => 'Usuário ou e-mail já cadastrado.'])->withInput();
        }

        User::query()->create([
            'name' => trim($data['name']),
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'active' => true,
            'disabled' => false,
        ]);

        $logger->log('Usuário criado', ['username' => $username, 'role' => $data['role']]);
        return back()->with('success', 'Usuário criado.');
    }

    public function update(Request $request, string $id, ActivityLogger $logger): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:180'],
            'role' => ['required','string','max:80'],
            'active' => ['nullable','boolean'],
            'password' => ['nullable','string','min:8'],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $duplicate = User::query()->where('email', $email)->where('_id', '!=', $user->getKey())->exists();
        if ($duplicate) {
            return back()->withErrors(['email' => 'E-mail já utilizado.']);
        }

        $payload = [
            'name' => trim($data['name']),
            'email' => $email,
            'role' => $data['role'],
            'active' => $request->boolean('active'),
            'disabled' => !$request->boolean('active'),
        ];
        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $payload['hashed_password'] = null;
            $payload['password_hash'] = null;
        }

        $user->fill($payload)->save();
        $logger->log('Usuário atualizado', ['username' => $user->username, 'role' => $data['role']]);
        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, string $id, ActivityLogger $logger): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_if((string) $user->getKey() === (string) $request->user()->getKey(), 422, 'Você não pode excluir sua própria conta.');

        if ($user->normalizedRole() === 'admin' && User::query()->where('role', 'admin')->where('active', true)->count() <= 1) {
            return back()->withErrors(['user' => 'O último administrador ativo não pode ser excluído.']);
        }

        $username = $user->username ?? $user->email;
        $user->delete();
        $logger->log('Usuário excluído', ['username' => $username]);
        return back()->with('success', 'Usuário excluído.');
    }
}
