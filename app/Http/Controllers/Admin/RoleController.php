<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\AccessService;
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

    public function store(Request $request, ActivityLogger $logger, AccessService $access): RedirectResponse
    {
        $data = $request->validate([
            'slug' => ['required','regex:/^[a-z0-9_]+$/','max:80'],
            'name' => ['required','string','max:120'],
            'permissions' => ['nullable','array','max:200'],
            'permissions.*' => ['string','max:120'],
        ]);

        $actor = $request->user();
        $slug = (string) $data['slug'];

        if ($slug === 'admin' && $actor->normalizedRole() !== 'admin') {
            abort(403, 'Apenas administradores podem alterar o perfil de administrador.');
        }

        $allowed = array_keys(config('modules.modules', []));
        $permissions = array_values(array_unique(array_intersect($data['permissions'] ?? [], $allowed)));

        if ($actor->normalizedRole() !== 'admin') {
            foreach ($permissions as $permission) {
                abort_if(!$access->can($actor, $permission), 403, 'Você não pode conceder permissões superiores às suas.');
            }
        }

        $role = Role::query()->where('slug', $slug)->first();
        $payload = [
            'slug' => $slug,
            'name' => trim($data['name']),
            'permissions' => $slug === 'admin' ? ['*'] : $permissions,
            'system' => in_array($slug, array_keys(config('modules.defaults', [])), true),
        ];

        if ($role) {
            $role->fill($payload)->save();
        } else {
            Role::query()->create($payload);
        }

        $logger->log('Perfil atualizado', ['role' => $slug, 'permissions' => $payload['permissions']]);

        return back()->with('success', 'Perfil salvo.');
    }
}
