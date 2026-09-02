@extends('layouts.app')
@section('title','Churn e base ativa')
@section('page-title','Churn e base ativa')
@section('page-subtitle','Evolução mensal baseada nas métricas oficiais de faturamento.')

@section('content')
<div class="panel">
<div class="table-wrap"><table>
<thead><tr><th>Período</th><th>Clientes</th><th>Veículos ativos</th><th>Ativações</th><th>Desativações</th><th>Churn líquido</th><th>Receita</th></tr></thead>
<tbody>
@forelse($periods as $row)
<tr><td>{{ $row['period'] }}</td><td>{{ $row['clients'] }}</td><td>{{ number_format($row['vehicles'],0,',','.') }}</td><td class="stat-positive">{{ $row['activations'] }}</td><td class="stat-negative">{{ $row['deactivations'] }}</td><td>{{ $row['activations']-$row['deactivations'] }}</td><td>R$ {{ number_format($row['revenue'],2,',','.') }}</td></tr>
@empty<tr><td colspan="7" class="empty">Ainda não há métricas mensais. Processe faturamentos para alimentar esta visão.</td></tr>@endforelse
</tbody></table></div>
</div>
@endsection
