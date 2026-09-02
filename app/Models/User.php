<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $table = 'users';
    protected $guarded = [];
    protected $hidden = [
        'password',
        'hashed_password',
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'active' => 'boolean',
        'disabled' => 'boolean',
        'permissions' => 'array',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) ($this->password ?? $this->hashed_password ?? $this->password_hash ?? '');
    }

    public function isActive(): bool
    {
        return ($this->active ?? true) !== false && ($this->disabled ?? false) !== true;
    }

    public function normalizedRole(): string
    {
        $value = strtolower(trim((string) ($this->role ?? 'user')));
        if ($value === '') {
            return 'user';
        }

        return match ($value) {
            'admin', 'administrador' => 'admin',
            'head_comercial', 'head comercial' => 'head_comercial',
            'financeiro' => 'financeiro',
            'operacional', 'operacao', 'operação' => 'operacional',
            'user', 'usuario', 'usuário', 'comercial' => 'user',
            default => preg_replace('/[^a-z0-9_]+/', '_', $value) ?: 'user',
        };
    }
}
