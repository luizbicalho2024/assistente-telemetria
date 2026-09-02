@extends('layouts.app')
@section('title','Ajuda')
@section('page-title','Ajuda e documentação')
@section('page-subtitle','Arquitetura e rotinas principais do Assistente Telemetria.')

@section('content')
<div class="grid-2">
<div class="panel">
<h2>Arquitetura</h2>
<ul class="help-list">
<li>Um único projeto Laravel atende Comercial, Financeiro e Operacional.</li>
<li>MongoDB é o banco primário da aplicação.</li>
<li>Perfis e permissões controlam o menu e também bloqueiam a rota no servidor.</li>
<li>O Docker Compose possui somente dois serviços: aplicação PHP/Apache e MongoDB.</li>
<li>Logs de auditoria registram operações críticas.</li>
</ul>
</div>
<div class="panel">
<h2>Migração dos sistemas antigos</h2>
<p>Depois de configurar as URIs dos bancos legados no <code>.env</code>, execute dentro do container:</p>
<div class="code-box">docker compose exec assistente php artisan legacy:import</div>
<p>Antes de gravar, é possível simular:</p>
<div class="code-box">docker compose exec assistente php artisan legacy:import --dry-run</div>
</div>
<div class="panel">
<h2>Comandos úteis</h2>
<div class="code-box">docker compose ps
docker compose logs -f assistente
docker compose exec assistente php artisan app:bootstrap
docker compose exec assistente php artisan optimize:clear
docker compose restart assistente</div>
</div>
<div class="panel">
<h2>Segurança</h2>
<ul class="help-list">
<li>Nunca versionar <code>.env</code>.</li>
<li>Trocar as senhas padrão do MongoDB e do administrador antes do deploy.</li>
<li>Publicar a aplicação atrás de HTTPS quando for instalada na VPS.</li>
<li>Não expor a porta 27017 do MongoDB para a Internet.</li>
</ul>
</div>
</div>
@endsection
