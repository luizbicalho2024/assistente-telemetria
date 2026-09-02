@extends('layouts.app')
@section('title','Comissão de vendedores')
@section('page-title','Comissão de vendedores')
@section('page-subtitle','Base de vendas aprovadas por consultor; percentuais podem ser incorporados à política de comissão.')

@section('content')
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Consultor</th><th>Propostas aprovadas</th><th>Receita contratada</th></tr></thead><tbody>@forelse($rows as $r)<tr><td>{{ $r['consultant'] }}</td><td>{{ $r['count'] }}</td><td>R$ {{ number_format($r['revenue'],2,',','.') }}</td></tr>@empty<tr><td colspan="3" class="empty">Sem vendas aprovadas.</td></tr>@endforelse</tbody></table></div></div>
@endsection
