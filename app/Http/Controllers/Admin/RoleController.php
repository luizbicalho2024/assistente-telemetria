<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $stored = Role::query()->get()->keyBy('slug');
        $roles = collect(config('modules.defaults', []))->map(function ($permissions, $slug) use ($stored) {
            $record = $stored->get($slug);
            return [
                'slug' => $slug,
                'name' => $record?->name ?? match ($slug) {
                    'admin' => 'Administrador',
                    'head_comercial' => 'Head Comercial',
                    'financeiro' => 'Financeiro',
                    'operacional' => 'Operacional',
                    default => 'Comercial',
                },
                'permissions' => $record?->permissions ?: $permissions,
                'system' => true,
            ];
        });

        foreach ($stored as $slug => $record) {
            if (!$roles->has($slug)) {
                $roles[$slug] = [
                    'slug' => $slug,
                    'name' => $record->name,
                    'permissions' => $record->permissions ?? [],
                    'system' => (bool) ($record->system ?? false),
                ];
            }
        }

        return view('admin.roles.index', [
            'roles' => $roles,
            'modules' => config('modules.modules', []),
            'groups' => config('modules.groups', []),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required','regex:/^[a-z0-9_]+$/','max:80'],
            'name' => ['required','string','max:120'],
            'permissions' => ['nullable','array'],
            'permissions.*' => ['string'],
        ]);

        $allowed = array_keys(config('modules.modules', []));
        $permissions = array_values(array_intersect($data['permissions'] ?? [], $allowed));

        $role = Role::query()->where('slug', $data['slug'])->first();
        $payload = [
            'slug' => $data['slug'],
            'name' => trim($data['name']),
            'permissions' => $data['slug'] === 'admin' ? ['*'] : $permissions,
            'system' => in_array($data['slug'], array_keys(config('modules.defaults', [])), true),
        ];
        if ($role) {
            $role->fill($payload)->save();
        } else {
            Role::query()->create($payload);
        }

        $logger->log('Perfil atualizado', ['role' => $data['slug'], 'permissions' => $payload['permissions']]);
        return back()->with('success', 'Perfil salvo.');
    }
}
