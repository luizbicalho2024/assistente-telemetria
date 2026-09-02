@extends('layouts.app')
@section('title','Pesquisa de mercado')
@section('page-title','Pesquisa de mercado')
@section('page-subtitle','Registro de concorrentes, produtos e referências comerciais.')

@section('content')
<div class="panel"><form method="post" action="{{ route('module.mercado.store') }}" class="form-grid">@csrf<label>Empresa<input name="empresa" required></label><label>Produto<input name="produto" required></label><label>Preço observado<input type="number" step="0.01" min="0" name="preco"></label><label class="span-3">Observações<textarea name="observacoes" rows="3"></textarea></label><div class="span-3"><button class="button primary">Registrar</button></div></form></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Empresa</th><th>Produto</th><th>Preço</th><th>Observações</th><th>Data</th></tr></thead><tbody>@forelse($records as $r)<tr><td>{{ $r->empresa }}</td><td>{{ $r->produto }}</td><td>R$ {{ number_format((float)($r->preco??0),2,',','.') }}</td><td>{{ $r->observacoes }}</td><td>{{ optional($r->created_at)->format('d/m/Y') }}</td></tr>@empty<tr><td colspan="5" class="empty">Sem registros.</td></tr>@endforelse</tbody></table></div></div>
@endsection
