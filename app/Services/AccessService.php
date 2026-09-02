<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

class AccessService
{
    public function permissionsFor(User $user): array
    {
        $role = $user->normalizedRole();

        $stored = Role::query()->where('slug', $role)->first();
        $permissions = is_array($stored?->permissions) ? $stored->permissions : [];

        if (!$permissions) {
            $permissions = config("modules.defaults.{$role}", []);
        }

        $direct = is_array($user->permissions ?? null) ? $user->permissions : [];
        return array_values(array_unique(array_merge($permissions, $direct)));
    }

    public function can(User $user, string $permission): bool
    {
        $permissions = $this->permissionsFor($user);
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function visibleModules(User $user): array
    {
        $result = [];
        foreach (config('modules.modules', []) as $permission => $module) {
            if ($this->can($user, $permission)) {
                $result[$permission] = $module;
            }
        }
        return $result;
    }
}
