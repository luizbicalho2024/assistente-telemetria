@extends('layouts.app')
@section('title','Dados de clientes')
@section('page-title','Dados de clientes')
@section('page-subtitle','Base operacional compartilhada pelo Comercial e Financeiro.')

@section('content')
<div class="panel"><form method="post" action="{{ route('module.clientes.store') }}" class="form-grid">@csrf<label>Nome / Razão social<input name="name" required></label><label>Documento<input name="document"></label><label>E-mail<input type="email" name="email"></label><label>Telefone<input name="phone"></label><label class="span-2">Observações<input name="notes"></label><div class="span-3"><button class="button primary">Salvar cliente</button></div></form></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Documento</th><th>E-mail</th><th>Telefone</th><th>Observações</th></tr></thead><tbody>@forelse($records as $r)<tr><td>{{ $r->name }}</td><td>{{ $r->document }}</td><td>{{ $r->email }}</td><td>{{ $r->phone }}</td><td>{{ $r->notes }}</td></tr>@empty<tr><td colspan="5" class="empty">Sem clientes cadastrados.</td></tr>@endforelse</tbody></table></div></div>
@endsection
