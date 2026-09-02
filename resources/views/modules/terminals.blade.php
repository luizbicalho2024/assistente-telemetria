@extends('layouts.app')
@section('title','Análise de terminais')
@section('page-title','Análise de terminais')
@section('page-subtitle','Visão consolidada do inventário de rastreadores.')

@section('content')
<div class="metrics-grid">
<div class="metric"><span>Total</span><strong>{{ number_format($trackers->count(),0,',','.') }}</strong></div>
<div class="metric"><span>GPRS/GSM</span><strong>{{ $trackers->filter(fn($x)=>str_contains(strtoupper((string)($x->Tipo??$x->tipo??'')),'GPRS')||str_contains(strtoupper((string)($x->Tipo??$x->tipo??'')),'GSM'))->count() }}</strong></div>
<div class="metric"><span>Satélite</span><strong>{{ $trackers->filter(fn($x)=>str_contains(strtoupper((string)($x->Tipo??$x->tipo??'')),'SATEL'))->count() }}</strong></div>
<div class="metric"><span>Modelos</span><strong>{{ $trackers->map(fn($x)=>$x->Modelo??$x->modelo)->filter()->unique()->count() }}</strong></div>
</div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Equipamento</th><th>Modelo</th><th>Tipo</th><th>Terminal</th><th>Chip</th></tr></thead><tbody>@foreach($trackers as $x)<tr><td>{{ $x->{'Nº Equipamento'}??$x->equipamento }}</td><td>{{ $x->Modelo??$x->modelo }}</td><td>{{ $x->Tipo??$x->tipo }}</td><td>{{ $x->Terminal??$x->terminal }}</td><td>{{ $x->Chip??$x->chip }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
