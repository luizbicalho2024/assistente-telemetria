@extends('layouts.app')
@section('title','Licitações e editais')
@section('page-title','Licitações e editais')
@section('page-subtitle','Simulação de custos para oportunidades públicas.')

@section('content')
<div class="panel">
<form method="post" action="{{ route('simulator.licitacao.calculate') }}" class="form-grid">
@csrf
<label>Órgão / cliente<input name="orgao" value="{{ old('orgao') }}" required></label>
<label>Produto<select name="product">@foreach($config['PRECO_CUSTO_LICITACAO'] as $p=>$v)<option value="{{ $p }}">{{ $p }} — custo R$ {{ number_format($v,2,',','.') }}</option>@endforeach</select></label>
<label>Quantidade<input type="number" name="quantity" min="1" value="{{ old('quantity',10) }}"></label>
<label>Mensalidade unitária (R$)<input type="number" step="0.01" name="sale_price" min="0" value="{{ old('sale_price',100) }}"></label>
<label>Prazo em meses<input type="number" name="months" min="1" max="120" value="{{ old('months',12) }}"></label>
<div class="span-3 actions"><button class="button primary">Calcular e registrar</button></div>
</form>
</div>
@if($result)
<div class="metrics-grid">
<div class="metric"><span>Receita contratual</span><strong>R$ {{ number_format($result['totalRevenue'],2,',','.') }}</strong></div>
<div class="metric"><span>Custo hardware</span><strong>R$ {{ number_format($result['hardwareCost'],2,',','.') }}</strong></div>
<div class="metric"><span>Margem indicativa</span><strong>{{ number_format((float)($result['margin']??0),2,',','.') }}%</strong></div>
<div class="metric"><span>Status inicial</span><strong>Aprovação</strong><small>Licitações passam pelo fluxo comercial</small></div>
</div>
@endif
@endsection
