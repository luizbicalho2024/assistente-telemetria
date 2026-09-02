@extends('layouts.app')
@section('title','Simulador PJ')
@section('page-title','Simulador PJ')
@section('page-subtitle','Precificação, custos, instalação, margem e fluxo de aprovação.')

@section('content')
<div class="panel">
    <div class="panel-header"><div><h2>Nova simulação PJ</h2><p>Margem personalizada abaixo do piso comercial entra automaticamente em aprovação.</p></div></div>
    <form method="post" action="{{ route('simulator.pj.calculate') }}" class="form-grid">
        @csrf
        <label>Empresa<input name="empresa" value="{{ old('empresa') }}" required></label>
        <label>Consultor<input name="consultor" value="{{ old('consultor', auth()->user()->name) }}"></label>
        <label>Plano
            <select name="plan" id="pj-plan" required>
                @foreach($config['PLANOS_PJ'] as $plan=>$products)<option value="{{ $plan }}" @selected(old('plan')===$plan)>{{ $plan }}</option>@endforeach
            </select>
        </label>
        <label>Produto
            <select name="product" required>
                @foreach(collect($config['PLANOS_PJ'])->flatMap(fn($v)=>array_keys($v))->unique() as $product)
                    <option value="{{ $product }}" @selected(old('product')===$product)>{{ $product }}</option>
                @endforeach
            </select>
        </label>
        <label>Quantidade de veículos<input type="number" name="vehicles" min="1" value="{{ old('vehicles', 10) }}" required></label>
        <label>Prazo (meses)<input type="number" name="months" min="1" max="120" value="{{ old('months', 12) }}" required></label>
        <label>Mensalidade por veículo (R$)<input type="number" name="sale_price" step="0.01" min="0" value="{{ old('sale_price', 80.88) }}" required></label>
        <label>Instalação cobrada por veículo (R$)<input type="number" name="installation_sale" step="0.01" min="0" value="{{ old('installation_sale', 0) }}"></label>
        <label class="checkbox"><input type="hidden" name="charge_installation" value="0"><input type="checkbox" name="charge_installation" value="1" @checked(old('charge_installation', true))> Cobrar instalação do cliente</label>
        <div class="span-3 actions"><button class="button primary" type="submit">Calcular e registrar proposta</button></div>
    </form>
</div>

@if($result)
<div class="metrics-grid">
    <div class="metric"><span>Receita total</span><strong>R$ {{ number_format($result['total_revenue'],2,',','.') }}</strong><small>{{ $result['vehicles'] }} veículos / {{ $result['months'] }} meses</small></div>
    <div class="metric"><span>Custo total</span><strong>R$ {{ number_format($result['total_cost'],2,',','.') }}</strong><small>Inclui hardware, instalação e fixos</small></div>
    <div class="metric"><span>Margem total</span><strong>R$ {{ number_format($result['total_margin'],2,',','.') }}</strong><small>{{ number_format((float)($result['margin_percent']??0),2,',','.') }}%</small></div>
    <div class="metric"><span>Status</span><strong>{{ $requiresApproval ? 'Aprovação' : 'Aprovada' }}</strong><small>Piso: {{ number_format($minimum,2,',','.') }}%</small></div>
</div>
<div class="panel">
    <h2>Detalhamento</h2>
    <div class="table-wrap"><table><tbody>
        <tr><th>Receita mensal</th><td>R$ {{ number_format($result['monthly_revenue'],2,',','.') }}</td><th>Custo mensal</th><td>R$ {{ number_format($result['monthly_cost'],2,',','.') }}</td></tr>
        <tr><th>Receita instalação</th><td>R$ {{ number_format($result['installation_revenue'],2,',','.') }}</td><th>Custo instalação</th><td>R$ {{ number_format($result['installation_cost'],2,',','.') }}</td></tr>
        <tr><th>Custo único</th><td>R$ {{ number_format($result['one_time_cost'],2,',','.') }}</td><th>Custo fixo</th><td>R$ {{ number_format($result['fixed_cost'],2,',','.') }}</td></tr>
        <tr><th>Payback instalação</th><td>{{ $result['payback_months'] !== null ? number_format($result['payback_months'],2,',','.').' meses' : 'Não aplicável' }}</td><th>Código</th><td>{{ $proposal->proposal_code }}</td></tr>
    </tbody></table></div>
</div>
@endif
@endsection
