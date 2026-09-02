@extends('layouts.app')
@section('title','Perfis e permissões')
@section('page-title','Perfis e permissões')
@section('page-subtitle','Controle exatamente quais páginas cada perfil pode acessar.')

@section('content')
@foreach($roles as $role)
<div class="panel">
<form method="post" action="{{ route('admin.roles.store') }}">@csrf
<div class="form-grid two"><label>Slug<input name="slug" value="{{ $role['slug'] }}" @readonly($role['system']) required></label><label>Nome<input name="name" value="{{ $role['name'] }}" required></label></div>
@if($role['slug']==='admin')
<div class="alert info">Administrador possui acesso total por definição.</div>
@else
@foreach($groups as $groupSlug=>$groupName)
<h3 style="margin-top:18px">{{ $groupName }}</h3>
<div class="permission-grid">
@foreach($modules as $permission=>$module)
@if($module['group']===$groupSlug)
<label class="permission-item"><input type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission,$role['permissions'],true))> {{ $module['label'] }}</label>
@endif
@endforeach
</div>
@endforeach
@endif
<div style="margin-top:16px"><button class="button primary">Salvar perfil</button></div>
</form>
</div>
@endforeach

<div class="panel">
<h2>Novo perfil</h2><p>Depois de criar, atribua o perfil a usuários na página de usuários.</p>
<form method="post" action="{{ route('admin.roles.store') }}" class="form-grid">@csrf
<label>Slug<input name="slug" placeholder="ex: diretoria" pattern="[a-z0-9_]+" required></label><label>Nome<input name="name" placeholder="Diretoria" required></label>
<div class="span-3 permission-grid">@foreach($modules as $permission=>$module)<label class="permission-item"><input type="checkbox" name="permissions[]" value="{{ $permission }}"> {{ $module['label'] }}</label>@endforeach</div>
<div class="span-3"><button class="button primary">Criar perfil</button></div>
</form>
</div>
@endsection
