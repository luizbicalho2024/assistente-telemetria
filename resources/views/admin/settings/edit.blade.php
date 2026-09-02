@extends('layouts.app')
@section('title','Identidade visual e preços')
@section('page-title','Configurações do sistema')
@section('page-subtitle','Identidade visual e regras comerciais centralizadas no MongoDB.')

@section('content')
<div class="panel">
<div class="panel-header"><div><h2>Identidade visual</h2><p>As duas logomarcas podem ser independentes: login/conteúdo e barra lateral.</p></div></div>
<form method="post" action="{{ route('admin.settings.branding') }}" enctype="multipart/form-data" class="form-grid">
@csrf @method('PUT')
<label>Nome do sistema<input name="system_name" value="{{ $settings['system_name'] }}" required></label>
<label class="span-2">Subtítulo<input name="system_subtitle" value="{{ $settings['system_subtitle'] }}" required></label>
<label class="span-3">Rodapé<input name="footer_text" value="{{ $settings['footer_text'] }}"></label>
<label>Logo principal<input type="file" name="logo" accept="image/*"></label>
<label>Logo da sidebar<input type="file" name="sidebar_logo" accept="image/*"></label>
<div></div>
@foreach([
'primary_color'=>'Primária','secondary_color'=>'Secundária','accent_color'=>'Destaque',
'background_color'=>'Fundo','surface_color'=>'Superfície','text_color'=>'Texto',
'muted_color'=>'Texto secundário','sidebar_background_color'=>'Fundo sidebar',
'sidebar_text_color'=>'Texto sidebar','sidebar_hover_color'=>'Hover sidebar',
'sidebar_active_color'=>'Item ativo sidebar'
] as $field=>$label)
<label class="color-field">{{ $label }}<input type="color" name="{{ $field }}" value="{{ $settings[$field] ?? '#000000' }}"></label>
@endforeach
<div class="span-3"><button class="button primary">Salvar identidade visual</button></div>
</form>
</div>

<div class="panel">
<div class="panel-header"><div><h2>Política comercial PJ</h2><p>Os cálculos mantêm as regras do Simulador: margem sobre preço de venda, instalação e custos únicos/recorrentes.</p></div></div>
<form method="post" action="{{ route('admin.settings.pricing') }}" class="stack">
@csrf @method('PUT')
<div class="form-grid">
<label>Margem mínima personalizada (%)<input type="number" step="0.01" min="30" max="99" name="minimum_margin" value="{{ $pricing['MARGEM_MINIMA_PERSONALIZADA_PJ'] }}"></label>
<label>Custo fixo implantação (R$)<input type="number" step="0.01" min="0" name="fixed_cost" value="{{ $pricing['CUSTO_FIXO_IMPLANTACAO_PJ'] }}"></label>
<label>Amortização hardware (meses)<input type="number" min="1" max="120" name="amortization_months" value="{{ $pricing['AMORTIZACAO_HARDWARE_MESES'] }}"></label>
</div>

@foreach($pricing['PLANOS_PJ'] as $plan=>$products)
<details @if($loop->first) open @endif>
<summary>{{ $plan }}</summary>
<div class="table-wrap" style="margin-top:10px"><table>
<thead><tr><th>Produto</th><th>Preço mensal</th><th>Custo mensal equivalente</th><th>Instalação venda</th><th>Instalação custo</th></tr></thead>
<tbody>
@foreach($products as $product=>$price)
<tr>
<td>{{ $product }}</td>
<td><input type="number" step="0.01" min="0" name="plan_prices[{{ $plan }}][{{ $product }}]" value="{{ $price }}"></td>
<td><input type="number" step="0.01" min="0" name="plan_costs[{{ $plan }}][{{ $product }}]" value="{{ $pricing['CUSTOS_PJ'][$plan][$product] ?? 0 }}"></td>
<td><input type="number" step="0.01" min="0" name="installation_sale[{{ $product }}]" value="{{ $pricing['INSTALACAO_PJ'][$product]['preco_venda'] ?? 0 }}"></td>
<td><input type="number" step="0.01" min="0" name="installation_cost[{{ $product }}]" value="{{ $pricing['INSTALACAO_PJ'][$product]['custo'] ?? 0 }}"></td>
</tr>
@endforeach
</tbody></table></div>
</details>
@endforeach

<details>
<summary>Custos detalhados PJ</summary>
<p>Formato preservado do projeto anterior: produto → lista de despesas com <code>despesa</code>, <code>incidencia</code>, <code>valor</code> e <code>observacao</code>. Incidências válidas: "Mensal por veículo" e "Único por veículo".</p>
<label>JSON
<textarea name="detailed_costs_json" rows="16">{{ json_encode($pricing['CUSTOS_DETALHADOS_PJ'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</textarea>
</label>
</details>

<details>
<summary>Preços PF</summary>
<div class="form-grid" style="margin-top:10px">@foreach($pricing['PRECOS_PF'] as $product=>$price)<label>{{ $product }}<input type="number" step="0.01" min="0" name="pf_prices[{{ $product }}]" value="{{ $price }}"></label>@endforeach</div>
</details>

<details>
<summary>Custos de referência — licitações</summary>
<div class="form-grid" style="margin-top:10px">@foreach($pricing['PRECO_CUSTO_LICITACAO'] as $product=>$price)<label>{{ $product }}<input type="number" step="0.01" min="0" name="auction_costs[{{ $product }}]" value="{{ $price }}"></label>@endforeach</div>
</details>

<div><button class="button primary">Salvar política comercial</button></div>
</form>
</div>
@endsection
