<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessService;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name', 'asc')->get(),
            'roles' => $this->roleSlugs(),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger, AccessService $access): RedirectResponse
    {
        $roles = $this->roleSlugs();
        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'username' => ['required','string','min:3','max:80','regex:/^[a-zA-Z0-9._-]+$/'],
            'email' => ['required','email','max:180'],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in($roles)],
        ]);

        $actor = $request->user();
        $this->assertCanAssignRole($actor, (string) $data['role'], $access);

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

    public function update(Request $request, string $id, ActivityLogger $logger, AccessService $access): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $actor = $request->user();

        if ($user->normalizedRole() === 'admin' && $actor->normalizedRole() !== 'admin') {
            abort(403, 'Somente um administrador pode alterar outra conta administrativa.');
        }

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'email' => ['required','email','max:180'],
            'role' => ['required', Rule::in($this->roleSlugs())],
            'active' => ['nullable','boolean'],
            'password' => ['nullable', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $active = $request->boolean('active');
        $isSelf = (string) $user->getKey() === (string) $actor->getKey();

        if ($isSelf && (!$active || $data['role'] !== $user->normalizedRole())) {
            return back()->withErrors(['user' => 'Por segurança, você não pode desativar sua própria conta nem alterar o próprio perfil.']);
        }

        $this->assertCanAssignRole($actor, (string) $data['role'], $access);

        if ($user->normalizedRole() === 'admin'
            && ($data['role'] !== 'admin' || !$active)
            && $this->activeAdminCount() <= 1) {
            return back()->withErrors(['user' => 'O último administrador ativo não pode ser desativado nem perder o perfil de administrador.']);
        }

        $email = mb_strtolower(trim($data['email']));
        $duplicate = User::query()->where('email', $email)->where('_id', '!=', $user->getKey())->exists();
        if ($duplicate) {
            return back()->withErrors(['email' => 'E-mail já utilizado.']);
        }

        $payload = [
            'name' => trim($data['name']),
            'email' => $email,
            'role' => $data['role'],
            'active' => $active,
            'disabled' => !$active,
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $payload['hashed_password'] = null;
            $payload['password_hash'] = null;
            $payload['remember_token'] = null;
        }

        $user->fill($payload)->save();
        $logger->log('Usuário atualizado', ['username' => $user->username, 'role' => $data['role']]);

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, string $id, ActivityLogger $logger): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        $actor = $request->user();

        abort_if((string) $user->getKey() === (string) $actor->getKey(), 422, 'Você não pode excluir sua própria conta.');

        if ($user->normalizedRole() === 'admin' && $actor->normalizedRole() !== 'admin') {
            abort(403, 'Somente um administrador pode excluir outra conta administrativa.');
        }

        if ($user->normalizedRole() === 'admin' && $this->activeAdminCount() <= 1) {
            return back()->withErrors(['user' => 'O último administrador ativo não pode ser excluído.']);
        }

        $username = $user->username ?? $user->email;
        $user->delete();
        $logger->log('Usuário excluído', ['username' => $username]);

        return back()->with('success', 'Usuário excluído.');
    }

    private function roleSlugs(): array
    {
        return collect(array_keys(config('modules.defaults', [])))
            ->merge(Role::query()->pluck('slug'))
            ->filter(fn ($slug) => is_string($slug) && preg_match('/^[a-z0-9_]+$/', $slug))
            ->unique()
            ->values()
            ->all();
    }

    private function assertCanAssignRole(User $actor, string $role, AccessService $access): void
    {
        if ($actor->normalizedRole() === 'admin') {
            return;
        }

        abort_if($role === 'admin', 403, 'Apenas administradores podem conceder o perfil de administrador.');

        $stored = Role::query()->where('slug', $role)->first();
        $permissions = is_array($stored?->permissions)
            ? $stored->permissions
            : config("modules.defaults.{$role}", []);

        foreach ($permissions as $permission) {
            abort_if($permission === '*' || !$access->can($actor, $permission), 403, 'Você não pode conceder um perfil com privilégios superiores aos seus.');
        }
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('active', true)
            ->get()
            ->filter(fn (User $candidate) => $candidate->isActive() && $candidate->normalizedRole() === 'admin')
            ->count();
    }
}
