<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Services\ActivityLogger;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SimulatorController extends Controller
{
    public function pj(PricingService $pricing): View
    {
        return view('simulators.pj', ['config' => $pricing->config(), 'result' => null]);
    }

    public function calculatePj(Request $request, PricingService $pricing, ActivityLogger $logger): View
    {
        $data = $request->validate([
            'empresa' => ['required', 'string', 'max:180'],
            'consultor' => ['nullable', 'string', 'max:120'],
            'plan' => ['required', 'string'],
            'product' => ['required', 'string'],
            'vehicles' => ['required', 'integer', 'min:1', 'max:100000'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'installation_sale' => ['nullable', 'numeric', 'min:0'],
            'charge_installation' => ['nullable', 'boolean'],
        ]);

        $config = $pricing->config();
        $plan = $data['plan'];
        $product = $data['product'];
        $months = (int) $data['months'];
        $vehicles = (int) $data['vehicles'];

        $detailed = $config['CUSTOS_DETALHADOS_PJ'][$product] ?? [];
        $costSummary = $pricing->summarizeCostComponents($detailed, $months);
        $legacyCost = (float) ($config['CUSTOS_PJ'][$plan][$product] ?? 0);
        $monthlyCost = max($legacyCost, (float) $costSummary['monthly_equivalent']);
        $oneTime = (float) $costSummary['one_time_per_vehicle'];
        $installation = $config['INSTALACAO_PJ'][$product] ?? ['preco_venda' => 0, 'custo' => 0];
        $chargeInstallation = $request->boolean('charge_installation');

        $result = $pricing->proposalTotals(
            (float) $data['sale_price'],
            $monthlyCost,
            $months,
            $vehicles,
            (float) ($data['installation_sale'] ?? $installation['preco_venda'] ?? 0),
            (float) ($installation['custo'] ?? 0),
            $oneTime,
            $chargeInstallation,
            (float) ($config['CUSTO_FIXO_IMPLANTACAO_PJ'] ?? 0),
        );

        $minimum = max(PricingService::MIN_CUSTOM_MARGIN_PERCENT, (float) ($config['MARGEM_MINIMA_PERSONALIZADA_PJ'] ?? 30));
        $requiresApproval = ($result['margin_percent'] ?? -999) < $minimum;

        $proposal = Proposal::query()->create([
            'proposal_code' => 'PJ-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'tipo' => 'PJ',
            'empresa' => $data['empresa'],
            'consultor' => $data['consultor'] ?: ($request->user()->name ?? $request->user()->username),
            'submitted_by_username' => $request->user()->username ?? $request->user()->email,
            'status' => $requiresApproval ? 'pending_approval' : 'approved',
            'plan' => $plan,
            'product' => $product,
            'vehicles' => $vehicles,
            'months' => $months,
            'valor_total' => $result['total_revenue'],
            'financial_snapshot' => $result,
            'minimum_margin_percent' => $minimum,
            'data_geracao' => now(),
        ]);

        $logger->log('Simulação PJ gerada', [
            'proposal_code' => $proposal->proposal_code,
            'empresa' => $data['empresa'],
            'status' => $proposal->status,
        ]);

        return view('simulators.pj', compact('config', 'result', 'proposal', 'requiresApproval', 'minimum'));
    }

    public function pf(PricingService $pricing): View
    {
        return view('simulators.pf', ['config' => $pricing->config(), 'result' => null]);
    }

    public function calculatePf(Request $request, PricingService $pricing, ActivityLogger $logger): View
    {
        $data = $request->validate([
            'cliente' => ['required', 'string', 'max:180'],
            'product' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'installments' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $config = $pricing->config();
        $base = (float) ($config['PRECOS_PF'][$data['product']] ?? 0);
        $fee = (float) ($config['TAXAS_PARCELAMENTO_PF'][(string) $data['installments']] ?? 0);
        $subtotal = $base * (int) $data['quantity'];
        $total = round($subtotal * (1 + $fee), 2);
        $installment = round($total / max(1, (int) $data['installments']), 2);

        $result = compact('base', 'fee', 'subtotal', 'total', 'installment') + $data;

        Proposal::query()->create([
            'proposal_code' => 'PF-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'tipo' => 'PF',
            'empresa' => $data['cliente'],
            'consultor' => $request->user()->name ?? $request->user()->username,
            'submitted_by_username' => $request->user()->username ?? $request->user()->email,
            'status' => 'approved',
            'valor_total' => $total,
            'financial_snapshot' => $result,
            'data_geracao' => now(),
        ]);
        $logger->log('Simulação PF gerada', ['cliente' => $data['cliente'], 'total' => $total]);

        return view('simulators.pf', compact('config', 'result'));
    }

    public function licitacao(PricingService $pricing): View
    {
        return view('simulators.licitacao', ['config' => $pricing->config(), 'result' => null]);
    }

    public function calculateLicitacao(Request $request, PricingService $pricing, ActivityLogger $logger): View
    {
        $data = $request->validate([
            'orgao' => ['required', 'string', 'max:180'],
            'product' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'months' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $config = $pricing->config();
        $unitCost = (float) ($config['PRECO_CUSTO_LICITACAO'][$data['product']] ?? 0);
        $quantity = (int) $data['quantity'];
        $months = (int) $data['months'];
        $totalRevenue = round((float) $data['sale_price'] * $quantity * $months, 2);
        $hardwareCost = round($unitCost * $quantity, 2);
        $margin = $pricing->grossMarginPercent($totalRevenue, $hardwareCost);

        $result = compact('unitCost', 'quantity', 'months', 'totalRevenue', 'hardwareCost', 'margin') + $data;

        Proposal::query()->create([
            'proposal_code' => 'LIC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'tipo' => 'LICITACAO',
            'empresa' => $data['orgao'],
            'consultor' => $request->user()->name ?? $request->user()->username,
            'submitted_by_username' => $request->user()->username ?? $request->user()->email,
            'status' => 'pending_approval',
            'valor_total' => $totalRevenue,
            'financial_snapshot' => $result,
            'data_geracao' => now(),
        ]);
        $logger->log('Simulação de licitação gerada', ['orgao' => $data['orgao']]);

        return view('simulators.licitacao', compact('config', 'result'));
    }
}
