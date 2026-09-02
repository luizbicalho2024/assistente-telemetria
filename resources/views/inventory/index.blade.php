@extends('layouts.app')
@section('title','Gestão de estoque')
@section('page-title','Gestão de estoque')
@section('page-subtitle','Importação e consulta do inventário de rastreadores.')

@section('content')
<div class="grid-2">
<div class="panel">
<h2>Importar planilha</h2><p>Atualiza itens pelo Nº Equipamento. Aceita XLSX, XLS e CSV.</p>
<form method="post" action="{{ route('inventory.import') }}" enctype="multipart/form-data" class="stack">@csrf<label>Arquivo<input type="file" name="file" accept=".xlsx,.xls,.csv" required></label><button class="button primary">Importar / atualizar estoque</button></form>
</div>
<div class="panel">
<h2>Pesquisar</h2><form method="get" class="stack"><label>Equipamento, modelo ou tipo<input name="q" value="{{ request('q') }}"></label><div class="actions"><button class="button primary">Filtrar</button><a class="button outline" href="{{ route('inventory.index') }}">Limpar</a></div></form>
</div>
</div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Nº Equipamento</th><th>Modelo</th><th>Tipo</th><th>Terminal</th><th>Chip</th><th>Cliente</th></tr></thead><tbody>@forelse($items as $x)<tr><td>{{ $x->{'Nº Equipamento'}??$x->equipamento }}</td><td>{{ $x->Modelo??$x->modelo }}</td><td>{{ $x->Tipo??$x->tipo }}</td><td>{{ $x->Terminal??$x->terminal }}</td><td>{{ $x->Chip??$x->chip }}</td><td>{{ $x->Cliente??$x->cliente }}</td></tr>@empty<tr><td colspan="6" class="empty">Estoque vazio.</td></tr>@endforelse</tbody></table></div></div>
@endsection
