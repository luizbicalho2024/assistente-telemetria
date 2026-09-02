@extends('layouts.app')
@section('title','Aprovações comerciais')
@section('page-title','Aprovações comerciais')
@section('page-subtitle','Propostas abaixo do piso comercial ou enviadas para análise.')

@section('content')
@if(!$isApprover)<div class="alert info">Seu perfil pode consultar as pendências, mas somente Head Comercial ou Administrador pode decidir.</div>@endif
@forelse($proposals as $p)
<div class="panel">
<div class="panel-header"><div><h2>{{ $p->proposal_code }} — {{ $p->empresa }}</h2><p>{{ $p->tipo }} · {{ $p->consultor }} · R$ {{ number_format((float)($p->valor_total??0),2,',','.') }}</p></div><span class="badge pending_approval">pending_approval</span></div>
@if(is_array($p->financial_snapshot ?? null))
<div class="metrics-grid">
<div class="metric"><span>Receita</span><strong>R$ {{ number_format((float)($p->financial_snapshot['total_revenue']??$p->valor_total??0),2,',','.') }}</strong></div>
<div class="metric"><span>Custo</span><strong>R$ {{ number_format((float)($p->financial_snapshot['total_cost']??0),2,',','.') }}</strong></div>
<div class="metric"><span>Margem</span><strong>{{ number_format((float)($p->financial_snapshot['margin_percent']??0),2,',','.') }}%</strong></div>
<div class="metric"><span>Piso</span><strong>{{ number_format((float)($p->minimum_margin_percent??30),2,',','.') }}%</strong></div>
</div>
@endif
@if($isApprover)
<div class="grid-2">
<form method="post" action="{{ route('proposals.approve',$p->getKey()) }}">@csrf<button class="button primary full" data-confirm="Aprovar esta proposta?">Aprovar</button></form>
<form method="post" action="{{ route('proposals.reject',$p->getKey()) }}" class="stack">@csrf<label>Motivo da rejeição<textarea name="reason" rows="2" required></textarea></label><button class="button danger">Rejeitar</button></form>
</div>
@endif
</div>
@empty<div class="panel"><div class="empty">Nenhuma proposta aguardando aprovação.</div></div>@endforelse
@endsection
