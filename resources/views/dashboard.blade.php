@extends('layouts.app')
@section('title', 'Visão geral')
@section('page-title', 'Visão geral')
@section('page-subtitle', 'Indicadores comerciais, financeiros e operacionais em uma única plataforma.')

@section('content')
<div class="hero">
    <div>
        <span class="eyebrow">Assistente Telemetria</span>
        <h1>Operação unificada</h1>
        <p>Simulação comercial, faturamento, estoque, churn, comandos e administração compartilhando o mesmo MongoDB.</p>
    </div>
</div>

<div class="metrics-grid">
    <div class="metric"><span>Propostas</span><strong>{{ number_format($proposalCount, 0, ',', '.') }}</strong><small>{{ $pendingProposals }} aguardando aprovação</small></div>
    <div class="metric"><span>Faturamento {{ $latestPeriod ?: '—' }}</span><strong>R$ {{ number_format($billingRevenue, 2, ',', '.') }}</strong><small>{{ $billingClients }} clientes</small></div>
    <div class="metric"><span>Veículos faturados</span><strong>{{ number_format($billingVehicles, 0, ',', '.') }}</strong><small>No período mais recente</small></div>
    <div class="metric"><span>Rastreadores / estoque</span><strong>{{ number_format($trackerCount, 0, ',', '.') }}</strong><small>Itens cadastrados</small></div>
</div>

<div class="panel">
    <div class="panel-header"><div><h2>Propostas recentes</h2><p>Últimos registros gerados pelos simuladores.</p></div></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Código</th><th>Empresa</th><th>Tipo</th><th>Consultor</th><th>Status</th><th>Valor</th><th>Data</th></tr></thead>
            <tbody>
            @forelse($recentProposals as $proposal)
                <tr>
                    <td>{{ $proposal->proposal_code ?? '—' }}</td>
                    <td>{{ $proposal->empresa ?? '—' }}</td>
                    <td>{{ $proposal->tipo ?? '—' }}</td>
                    <td>{{ $proposal->consultor ?? '—' }}</td>
                    <td><span class="badge {{ $proposal->status }}">{{ $proposal->status ?? '—' }}</span></td>
                    <td>R$ {{ number_format((float)($proposal->valor_total ?? 0), 2, ',', '.') }}</td>
                    <td>{{ optional($proposal->data_geracao)->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Nenhuma proposta registrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
