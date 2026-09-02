<?php

namespace App\Http\Middleware;

use App\Services\AccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user || !$user->isActive()) {
            abort(403, 'Usuário inativo ou sem sessão.');
        }

        foreach ($permissions as $permission) {
            if ($this->access->can($user, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'Seu perfil não possui permissão para acessar esta página.');
    }
}
