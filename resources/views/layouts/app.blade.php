<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $branding['system_name'] ?? 'Assistente Telemetria')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        :root {
            --primary: {{ $branding['primary_color'] ?? '#165DFF' }};
            --secondary: {{ $branding['secondary_color'] ?? '#0B1F33' }};
            --accent: {{ $branding['accent_color'] ?? '#00A884' }};
            --background: {{ $branding['background_color'] ?? '#F5F7FA' }};
            --surface: {{ $branding['surface_color'] ?? '#FFFFFF' }};
            --text: {{ $branding['text_color'] ?? '#172B4D' }};
            --muted: {{ $branding['muted_color'] ?? '#667085' }};
            --sidebar: {{ $branding['sidebar_background_color'] ?? '#0B1F33' }};
            --sidebar-text: {{ $branding['sidebar_text_color'] ?? '#F8FAFC' }};
            --sidebar-hover: {{ $branding['sidebar_hover_color'] ?? '#243B53' }};
            --sidebar-active: {{ $branding['sidebar_active_color'] ?? '#165DFF' }};
        }
    </style>
    @stack('head')
</head>
<body>
@auth
    @php
        $access = app(\App\Services\AccessService::class);
        $visibleModules = $access->visibleModules(auth()->user());
        $moduleGroups = collect($visibleModules)->groupBy(fn($m) => $m['group']);
        $groupLabels = config('modules.groups', []);
        $roleLabel = match(auth()->user()->normalizedRole()) {
            'admin' => 'Administrador',
            'head_comercial' => 'Head Comercial',
            'financeiro' => 'Financeiro',
            'operacional' => 'Operacional',
            default => \App\Models\Role::query()->where('slug', auth()->user()->normalizedRole())->value('name') ?: ucfirst(str_replace('_',' ',auth()->user()->normalizedRole())),
        };
    @endphp
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                @if(!empty($branding['sidebar_logo_base64']) || !empty($branding['logo_base64']))
                    @php($sideLogo = $branding['sidebar_logo_base64'] ?? $branding['logo_base64'])
                    @php($sideMime = $branding['sidebar_logo_mime'] ?? $branding['logo_mime'] ?? 'image/png')
                    <img src="data:{{ $sideMime }};base64,{{ $sideLogo }}" alt="Logo">
                @else
                    <div class="brand-mark">AT</div>
                @endif
                <div>
                    <strong>{{ $branding['system_name'] ?? 'Assistente Telemetria' }}</strong>
                    <small>{{ $branding['system_subtitle'] ?? '' }}</small>
                </div>
            </div>

            <div class="session-card">
                <strong>{{ auth()->user()->name ?? auth()->user()->username }}</strong>
                <span>{{ $roleLabel }}</span>
            </div>

            <nav>
                @foreach($moduleGroups as $group => $modules)
                    <div class="nav-group">
                        <div class="nav-caption">{{ $groupLabels[$group] ?? ucfirst($group) }}</div>
                        @foreach($modules as $permission => $module)
                            <a href="{{ route($module['route']) }}"
                               class="{{ request()->routeIs($module['route']) ? 'active' : '' }}">
                                {{ $module['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
                <div class="nav-group">
                    <a href="{{ route('help') }}" class="{{ request()->routeIs('help') ? 'active' : '' }}">Ajuda e documentação</a>
                </div>
            </nav>

            <form method="post" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit" class="button ghost full">Sair da plataforma</button>
            </form>
            @if(!empty($branding['footer_text']))
                <div class="sidebar-footer">{{ $branding['footer_text'] }}</div>
            @endif
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir menu">☰</button>
                <div>
                    <strong>@yield('page-title', 'Assistente Telemetria')</strong>
                    <small>@yield('page-subtitle')</small>
                </div>
            </header>
            <div class="content">
                @if(session('success'))
                    <div class="alert success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert danger">
                        <strong>Revise os dados:</strong>
                        <ul>
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
@else
    @yield('guest')
@endauth
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
