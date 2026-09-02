@extends('layouts.app')
@section('title','Simulador PF')
@section('page-title','Simulador PF')
@section('page-subtitle','Venda PF com parcelamento e taxas configuradas.')

@section('content')
<div class="panel">
<form method="post" action="{{ route('simulator.pf.calculate') }}" class="form-grid">
@csrf
<label>Cliente<input name="cliente" value="{{ old('cliente') }}" required></label>
<label>Produto<select name="product">@foreach($config['PRECOS_PF'] as $p=>$v)<option value="{{ $p }}">{{ $p }} — R$ {{ number_format($v,2,',','.') }}</option>@endforeach</select></label>
<label>Quantidade<input type="number" name="quantity" min="1" value="{{ old('quantity',1) }}"></label>
<label>Parcelas<select name="installments"><option value="1">1x sem taxa</option>@foreach($config['TAXAS_PARCELAMENTO_PF'] as $n=>$fee)<option value="{{ $n }}">{{ $n }}x — {{ number_format($fee*100,1,',','.') }}%</option>@endforeach</select></label>
<div class="span-2 actions"><button class="button primary">Calcular</button></div>
</form>
</div>
@if($result)
<div class="metrics-grid">
<div class="metric"><span>Preço base</span><strong>R$ {{ number_format($result['base'],2,',','.') }}</strong></div>
<div class="metric"><span>Subtotal</span><strong>R$ {{ number_format($result['subtotal'],2,',','.') }}</strong></div>
<div class="metric"><span>Total</span><strong>R$ {{ number_format($result['total'],2,',','.') }}</strong><small>Taxa {{ number_format($result['fee']*100,1,',','.') }}%</small></div>
<div class="metric"><span>Parcela</span><strong>R$ {{ number_format($result['installment'],2,',','.') }}</strong><small>{{ $result['installments'] }}x</small></div>
</div>
@endif
@endsection
