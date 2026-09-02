@extends('layouts.app')
@section('title','Faturamento de parceiros')
@section('page-title','Faturamento de parceiros')
@section('page-subtitle','Consolidação dos terminais associados a parceiros.')

@section('content')
<div class="metrics-grid">
<div class="metric"><span>Parceiros</span><strong>{{ $groups->count() }}</strong></div>
<div class="metric"><span>Terminais</span><strong>{{ $records->count() }}</strong></div>
<div class="metric"><span>Valor consolidado</span><strong>R$ {{ number_format((float)$groups->sum('total'),2,',','.') }}</strong></div>
</div>
<div class="panel"><div class="table-wrap"><table><thead><tr><th>Parceiro</th><th>Terminais</th><th>Total</th></tr></thead><tbody>@forelse($groups as $g)<tr><td>{{ $g['partner'] }}</td><td>{{ $g['terminals'] }}</td><td>R$ {{ number_format($g['total'],2,',','.') }}</td></tr>@empty<tr><td colspan="3" class="empty">Sem dados de parceiros.</td></tr>@endforelse</tbody></table></div></div>
@endsection
