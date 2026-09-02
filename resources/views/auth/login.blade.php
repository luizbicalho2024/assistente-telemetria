@extends('layouts.app')

@section('title', 'Acesso | '.($branding['system_name'] ?? 'Assistente Telemetria'))

@section('guest')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            @if(!empty($branding['logo_base64']))
                <img src="data:{{ $branding['logo_mime'] ?? 'image/png' }};base64,{{ $branding['logo_base64'] }}" alt="Logo">
            @else
                <div class="brand-mark large">AT</div>
            @endif
            <h1>{{ $branding['system_name'] ?? 'Assistente Telemetria' }}</h1>
            <p>{{ $branding['system_subtitle'] ?? 'Inteligência comercial, financeira e operacional' }}</p>
        </div>

        @if($errors->any())
            <div class="alert danger">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if($hasUsers)
            <form method="post" action="{{ route('login.perform') }}" class="stack">
                @csrf
                <label>Usuário ou e-mail
                    <input type="text" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
                </label>
                <label>Senha
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button class="button primary full" type="submit">Entrar</button>
            </form>
        @else
            <div class="alert info">Nenhum usuário encontrado. Crie o primeiro administrador.</div>
            <form method="post" action="{{ route('bootstrap.admin') }}" class="stack">
                @csrf
                <label>Nome
                    <input type="text" name="name" value="{{ old('name', 'Administrador') }}" required>
                </label>
                <label>Usuário
                    <input type="text" name="username" value="{{ old('username', 'admin') }}" required>
                </label>
                <label>E-mail
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label>Senha
                    <input type="password" name="password" minlength="8" required>
                </label>
                <label>Confirmar senha
                    <input type="password" name="password_confirmation" minlength="8" required>
                </label>
                <button class="button primary full" type="submit">Criar administrador</button>
            </form>
        @endif
    </div>
</div>
@endsection
