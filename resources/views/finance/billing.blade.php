@extends('layouts.app')
@section('title','Faturamento Verdio')
@section('page-title','Faturamento Verdio')
@section('page-subtitle','Carga de relatórios, cálculo mensal, histórico imutável e snapshots analíticos.')

@section('content')
<div class="grid-2">
<div class="panel">
<h2>Processar relatório</h2>
<p>A planilha precisa conter Cliente, Terminal, Equipamento, datas de ativação/desativação, dias ativos, suspensão e condição. O estoque define modelo/tipo e os contratos definem preço.</p>
<form method="post" action="{{ route('finance.billing.process') }}" enctype="multipart/form-data" class="stack">@csrf<label>Arquivos (um ou vários meses)<input type="file" name="files[]" accept=".xlsx,.xls,.csv" multiple required></label><button class="button primary">Processar e persistir faturamento</button></form>
</div>
<div class="panel">
<h2>Fechar mês</h2><p>Consolida a visão mensal após as cargas/revisões.</p>
<form method="post" action="{{ route('finance.billing.close') }}" class="stack">@csrf<label>Período YYYY-MM<input name="period_key" pattern="\d{4}-\d{2}" placeholder="2026-08" required></label><button class="button secondary">Fechar mês</button></form>
</div>
</div>
<div class="panel">
<h2>Processamentos recentes</h2>
<div class="table-wrap"><table><thead><tr><th>Período</th><th>Cliente</th><th>Terminais</th><th>Total</th><th>Revisão</th><th>Gerado por</th><th>Data</th></tr></thead><tbody>
@forelse($latest as $r)<tr><td>{{ $r->period_key??$r->periodo_relatorio }}</td><td>{{ $r->cliente }}</td><td>{{ count($r->itens_detalhados??[]) ?: (($r->terminais_cheio??0)+($r->terminais_proporcional??0)+($r->terminais_suspensos??0)) }}</td><td>R$ {{ number_format((float)($r->valor_total??0),2,',','.') }}</td><td>{{ $r->revision??1 }}</td><td>{{ $r->gerado_por }}</td><td>{{ optional($r->data_geracao)->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="7" class="empty">Nenhum faturamento.</td></tr>@endforelse
</tbody></table></div>
</div>
@endsection
