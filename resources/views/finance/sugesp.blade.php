@extends('layouts.app')
@section('title','Relatório SUGESP')
@section('page-title','Relatório SUGESP detalhado')
@section('page-subtitle','Visão consolidada de métricas mensais por cliente.')

@section('content')
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Período</th><th>Cliente</th><th>Receita</th><th>Faturados</th><th>Ativos fim mês</th><th>Ativações</th><th>Desativações</th><th>Suspensões</th><th>Qualidade</th></tr></thead><tbody>@forelse($metrics as $m)<tr><td>{{ $m->period_key }}</td><td>{{ $m->cliente }}</td><td>R$ {{ number_format((float)($m->receita??0),2,',','.') }}</td><td>{{ $m->veiculos_faturados??0 }}</td><td>{{ $m->veiculos_ativos_fim_mes??0 }}</td><td>{{ $m->ativacoes??0 }}</td><td>{{ $m->desativacoes??0 }}</td><td>{{ $m->suspensoes??0 }}</td><td>{{ $m->data_quality??'—' }}</td></tr>@empty<tr><td colspan="9" class="empty">Sem dados.</td></tr>@endforelse</tbody></table></div></div>
@endsection
