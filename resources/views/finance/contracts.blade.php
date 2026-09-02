@extends('layouts.app')
@section('title','Contratos de clientes')
@section('page-title','Contratos de clientes')
@section('page-subtitle','Termo de adesão, vencimento e preço personalizado por tipo de equipamento.')

@section('content')
<div class="panel">
<form method="post" action="{{ route('finance.contracts.store') }}" class="form-grid">@csrf
<label>Cliente<input name="cliente" required></label>
<label>Última atualização do termo<input type="date" name="ultima_atualizacao_termo" value="{{ now()->format('Y-m-d') }}" required></label>
<label>Prazo contratual (meses)<input type="number" name="prazo_contrato_meses" min="1" max="120" value="12" required></label>
<label>GPRS (R$)<input type="number" step="0.01" name="valor_gprs" min="0" value="0"></label>
<label>Satélite (R$)<input type="number" step="0.01" name="valor_satelite" min="0" value="0"></label>
<label>Câmera (R$)<input type="number" step="0.01" name="valor_camera" min="0" value="0"></label>
<label>Rádio (R$)<input type="number" step="0.01" name="valor_radio" min="0" value="0"></label>
<label>RFID (R$)<input type="number" step="0.01" name="valor_rfid" min="0" value="0"></label>
<label>CAN/Telemetria (R$)<input type="number" step="0.01" name="valor_can" min="0" value="0"></label>
<label>Vídeo (R$)<input type="number" step="0.01" name="valor_video" min="0" value="0"></label>
<label class="span-2">Observações<textarea name="observacoes" rows="2"></textarea></label>
<div class="span-3"><button class="button primary">Salvar / atualizar contrato</button></div>
</form>
</div>

<div class="panel">
<div class="table-wrap"><table>
<thead><tr><th>Cliente</th><th>Termo</th><th>Prazo</th><th>Vencimento</th><th>Status</th><th>Preços ativos</th></tr></thead>
<tbody>
@forelse($contracts as $c)
@php
    $p = $c->precos_por_tipo ?? $c->prices ?? [];
    $expiry = !empty($c->vencimento_contrato) ? \Illuminate\Support\Carbon::parse($c->vencimento_contrato) : null;
    $activePrices = collect($p)->filter(fn($v)=>(float)$v>0)->map(fn($v,$k)=>$k.': R$ '.number_format((float)$v,2,',','.'))->implode(' | ');
@endphp
<tr>
<td>{{ $c->cliente }}</td>
<td>{{ !empty($c->ultima_atualizacao_termo) ? \Illuminate\Support\Carbon::parse($c->ultima_atualizacao_termo)->format('d/m/Y') : '—' }}</td>
<td>{{ $c->prazo_contrato_meses ?? '—' }} meses</td>
<td>{{ $expiry?->format('d/m/Y') ?? '—' }}</td>
<td><span class="badge {{ $expiry && $expiry->isPast() ? 'rejected' : 'approved' }}">{{ $expiry && $expiry->isPast() ? 'Vencido' : 'Vigente' }}</span></td>
<td>{{ $activePrices ?: 'Nenhum valor fixo' }}</td>
</tr>
@empty<tr><td colspan="6" class="empty">Sem contratos cadastrados.</td></tr>@endforelse
</tbody></table></div>
</div>
@endsection
