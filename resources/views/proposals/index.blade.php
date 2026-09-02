@extends('layouts.app')
@section('title','Dashboard de propostas')
@section('page-title','Dashboard de propostas')
@section('page-subtitle','Volume, valor, perfil de venda e situação das propostas.')

@section('content')
<div class="panel">
<form method="get" class="form-grid">
<label>Status<select name="status"><option value="">Todos</option>@foreach(['approved'=>'Aprovada','pending_approval'=>'Pendente','rejected'=>'Rejeitada'] as $v=>$l)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $l }}</option>@endforeach</select></label>
<label>Tipo<input name="tipo" value="{{ request('tipo') }}" placeholder="PJ, PF, LICITACAO"></label>
<div class="actions"><button class="button primary">Filtrar</button><a class="button outline" href="{{ route('proposals.index') }}">Limpar</a></div>
</form>
</div>
<div class="panel">
<div class="table-wrap"><table>
<thead><tr><th>Código</th><th>Empresa</th><th>Tipo</th><th>Consultor</th><th>Status</th><th>Valor</th><th>Margem</th><th>Data</th></tr></thead>
<tbody>
@forelse($proposals as $p)
<tr>
<td>{{ $p->proposal_code }}</td><td>{{ $p->empresa }}</td><td>{{ $p->tipo }}</td><td>{{ $p->consultor }}</td>
<td><span class="badge {{ $p->status }}">{{ $p->status }}</span></td>
<td>R$ {{ number_format((float)($p->valor_total??0),2,',','.') }}</td>
<td>{{ isset($p->financial_snapshot['margin_percent']) ? number_format((float)$p->financial_snapshot['margin_percent'],2,',','.').'%' : '—' }}</td>
<td>{{ optional($p->data_geracao)->format('d/m/Y H:i') ?? '—' }}</td>
</tr>
@empty<tr><td colspan="8" class="empty">Nenhuma proposta.</td></tr>@endforelse
</tbody></table></div>
</div>
@endsection
