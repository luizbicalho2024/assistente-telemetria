@extends('layouts.app')
@section('title','Histórico de faturamento')
@section('page-title','Histórico de faturamento')
@section('page-subtitle','Snapshot vigente de cada cliente/período.')

@section('content')
<div class="panel"><form method="get" class="form-grid"><label>Período<input name="period" value="{{ request('period') }}" placeholder="YYYY-MM"></label><label>Cliente<input name="client" value="{{ request('client') }}"></label><div class="actions"><button class="button primary">Filtrar</button><a class="button outline" href="{{ route('finance.history') }}">Limpar</a></div></form></div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Período</th><th>Cliente</th><th>Cheio</th><th>Proporcional</th><th>Suspensos</th><th>GPRS</th><th>Satélite</th><th>Total</th><th>Rev.</th></tr></thead><tbody>@forelse($records as $r)<tr><td>{{ $r->period_key??$r->periodo_relatorio }}</td><td>{{ $r->cliente }}</td><td>{{ $r->terminais_cheio??0 }}</td><td>{{ $r->terminais_proporcional??0 }}</td><td>{{ $r->terminais_suspensos??0 }}</td><td>{{ $r->terminais_gprs??0 }}</td><td>{{ $r->terminais_satelitais??0 }}</td><td>R$ {{ number_format((float)($r->valor_total??0),2,',','.') }}</td><td>{{ $r->revision??1 }}</td></tr>@empty<tr><td colspan="9" class="empty">Sem histórico.</td></tr>@endforelse</tbody></table></div></div>
@endsection
