@extends('layouts.app')
@section('title','Resumo mensal')
@section('page-title','Resumo mensal de faturamento')
@section('page-subtitle','Receita, clientes, frota e movimentações por competência.')

@section('content')
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Período</th><th>Status</th><th>Clientes</th><th>Veículos faturados</th><th>Ativos fim do mês</th><th>Ativações</th><th>Desativações</th><th>Receita</th></tr></thead><tbody>
@forelse($summary as $r) @php($closure=$closures->get($r['period'])) <tr><td>{{ $r['period'] }}</td><td><span class="badge {{ $closure?'approved':'pending_approval' }}">{{ $closure?'Fechado':'Aberto' }}</span></td><td>{{ $r['clients'] }}</td><td>{{ $r['vehicles'] }}</td><td>{{ $r['active_end'] }}</td><td class="stat-positive">{{ $r['activations'] }}</td><td class="stat-negative">{{ $r['deactivations'] }}</td><td>R$ {{ number_format($r['revenue'],2,',','.') }}</td></tr>
@empty<tr><td colspan="8" class="empty">Sem métricas mensais.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
