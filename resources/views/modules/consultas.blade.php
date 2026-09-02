@extends('layouts.app')
@section('title','Consultas gerais')
@section('page-title','Consultas gerais')
@section('page-subtitle','Pesquisa centralizada em terminais e clientes.')

@section('content')
<div class="panel"><form method="get" class="form-grid two"><label>Pesquisar<input name="q" value="{{ $term }}" placeholder="Terminal, equipamento, modelo, cliente ou documento"></label><div class="actions"><button class="button primary">Pesquisar</button></div></form></div>
@if($term!=='')
<div class="grid-2">
<div class="panel"><h2>Terminais</h2><div class="table-wrap"><table><thead><tr><th>Equipamento</th><th>Modelo</th><th>Tipo</th></tr></thead><tbody>@forelse($trackers as $x)<tr><td>{{ $x->{'Nº Equipamento'} ?? $x->equipamento }}</td><td>{{ $x->Modelo ?? $x->modelo }}</td><td>{{ $x->Tipo ?? $x->tipo }}</td></tr>@empty<tr><td colspan="3" class="empty">Nada encontrado.</td></tr>@endforelse</tbody></table></div></div>
<div class="panel"><h2>Clientes</h2><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Documento</th><th>E-mail</th></tr></thead><tbody>@forelse($customers as $x)<tr><td>{{ $x->name ?? $x->cliente }}</td><td>{{ $x->document }}</td><td>{{ $x->email }}</td></tr>@empty<tr><td colspan="3" class="empty">Nada encontrado.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endif
@endsection
