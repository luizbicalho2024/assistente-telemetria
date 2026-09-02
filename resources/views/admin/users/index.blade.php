@extends('layouts.app')
@section('title','Usuários')
@section('page-title','Gerenciar usuários')
@section('page-subtitle','Uma única base de autenticação para todos os módulos.')

@section('content')
<div class="panel">
<h2>Novo usuário</h2>
<form method="post" action="{{ route('admin.users.store') }}" class="form-grid">@csrf
<label>Nome<input name="name" required></label><label>Usuário<input name="username" required></label><label>E-mail<input type="email" name="email" required></label>
<label>Senha<input type="password" name="password" minlength="8" required></label><label>Perfil<select name="role">@foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select></label>
<div class="actions"><button class="button primary">Criar usuário</button></div>
</form>
</div>
@foreach($users as $u)
<details>
<summary>{{ $u->name ?? $u->username }} — {{ $u->role }} — {{ $u->isActive() ? 'Ativo' : 'Inativo' }}</summary>
<form method="post" action="{{ route('admin.users.update',$u->getKey()) }}" class="form-grid" style="margin-top:12px">@csrf @method('PUT')
<label>Nome<input name="name" value="{{ $u->name }}" required></label><label>E-mail<input type="email" name="email" value="{{ $u->email }}" required></label>
<label>Perfil<select name="role">@foreach($roles as $role)<option value="{{ $role }}" @selected($u->normalizedRole()===$role)>{{ $role }}</option>@endforeach</select></label>
<label>Nova senha (opcional)<input type="password" name="password" minlength="8"></label>
<label class="checkbox"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($u->isActive())> Conta ativa</label>
<div class="actions"><button class="button primary small">Salvar</button></div>
</form>
@if((string)$u->getKey() !== (string)auth()->id())
<form method="post" action="{{ route('admin.users.destroy',$u->getKey()) }}" style="margin-top:8px">@csrf @method('DELETE')<button class="button danger small" data-confirm="Excluir este usuário?">Excluir</button></form>
@endif
</details>
@endforeach
@endsection
