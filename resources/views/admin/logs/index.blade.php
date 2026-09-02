@extends('layouts.app')
@section('title','Auditoria e logs')
@section('page-title','Auditoria e logs')
@section('page-subtitle','Ações críticas registradas pela aplicação.')

@section('content')
<div class="panel"><form method="get" class="form-grid two"><label>Usuário ou ação<input name="q" value="{{ request('q') }}"></label><div class="actions"><button class="button primary">Filtrar</button><a class="button outline" href="{{ route('admin.logs.index') }}">Limpar</a></div></form></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>IP</th><th>Detalhes</th></tr></thead><tbody>@forelse($logs as $l)<tr><td>{{ optional($l->timestamp)->format('d/m/Y H:i:s') }}</td><td>{{ $l->user }}</td><td>{{ $l->action }}</td><td>{{ $l->ip }}</td><td><code>{{ json_encode($l->details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</code></td></tr>@empty<tr><td colspan="5" class="empty">Sem logs.</td></tr>@endforelse</tbody></table></div></div>
@endsection
