<?php

namespace App\Http\Controllers;

use App\Models\BillingHistory;
use App\Models\BillingMonthClosure;
use App\Models\BillingMonthlyMetric;
use App\Models\ClientContract;
use App\Models\PartnerTerminal;
use App\Services\ActivityLogger;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function billing(): View
    {
        return view('finance.billing', [
            'latest' => BillingHistory::query()->orderBy('data_geracao', 'desc')->limit(30)->get(),
        ]);
    }

    public function processBilling(Request $request, BillingService $billing, ActivityLogger $logger): RedirectResponse
    {
        $request->validate([
            'files' => ['required','array','min:1','max:24'],
            'files.*' => ['required','file','mimes:xlsx,xls,csv','max:51200'],
        ]);

        $results = [];
        $failures = [];
        $user = $request->user()->username ?? $request->user()->email ?? 'sistema';

        foreach ($request->file('files', []) as $file) {
            try {
                $result = $billing->importAndCalculate($file, $user);
                $results[] = $result + ['file' => $file->getClientOriginalName()];
                $logger->log('Faturamento processado', [
                    'arquivo' => $file->getClientOriginalName(),
                    'period' => $result['period_key'],
                    'clients' => $result['clients'],
                    'total' => $result['total'],
                ]);
            } catch (\Throwable $e) {
                report($e);
                $failures[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }

        if (!$results) {
            return back()->withErrors(['files' => 'Nenhum arquivo foi processado. '.implode(' | ', $failures)]);
        }

        $periods = collect($results)->pluck('period_label')->unique()->implode(', ');
        $clients = collect($results)->sum('clients');
        $total = collect($results)->sum('total');
        $message = sprintf(
            '%d arquivo(s) processado(s) [%s]: %d registros de clientes, total R$ %s.',
            count($results),
            $periods,
            $clients,
            number_format((float) $total, 2, ',', '.')
        );
        if ($failures) {
            $message .= ' Falhas: '.implode(' | ', $failures);
        }

        return back()->with('success', $message);
    }

    public function closeBilling(Request $request, BillingService $billing, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate(['period_key' => ['required','regex:/^\d{4}-\d{2}$/']]);
        $closure = $billing->closeMonth($data['period_key'], $request->user()->username ?? $request->user()->email ?? 'sistema');
        $logger->log('Mês de faturamento fechado', ['period_key' => $closure->period_key]);
        return back()->with('success', 'Mês fechado com sucesso.');
    }

    public function history(Request $request): View
    {
        $query = BillingHistory::query()->orderBy('period_key', 'desc')->orderBy('cliente', 'asc');
        if ($request->filled('period')) {
            $query->where('period_key', $request->query('period'));
        }
        if ($request->filled('client')) {
            $term = trim((string) $request->query('client'));
            $query->where('cliente', 'like', "%{$term}%");
        }

        return view('finance.history', ['records' => $query->limit(1000)->get()]);
    }

    public function summary(): View
    {
        $metrics = BillingMonthlyMetric::query()->orderBy('period_key', 'desc')->get();
        $summary = $metrics->groupBy('period_key')->map(fn ($rows, $period) => [
            'period' => $period,
            'clients' => $rows->count(),
            'vehicles' => (int) $rows->sum('veiculos_faturados'),
            'active_end' => (int) $rows->sum('veiculos_ativos_fim_mes'),
            'activations' => (int) $rows->sum('ativacoes'),
            'deactivations' => (int) $rows->sum('desativacoes'),
            'revenue' => round((float) $rows->sum('receita'), 2),
        ])->values();

        return view('finance.summary', [
            'summary' => $summary,
            'closures' => BillingMonthClosure::query()->orderBy('period_key', 'desc')->get()->keyBy('period_key'),
        ]);
    }

    public function contracts(): View
    {
        return view('finance.contracts', [
            'contracts' => ClientContract::query()->orderBy('cliente', 'asc')->limit(1000)->get(),
        ]);
    }

    public function storeContract(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'cliente' => ['required','string','max:180'],
            'ultima_atualizacao_termo' => ['required','date'],
            'prazo_contrato_meses' => ['required','integer','min:1','max:120'],
            'valor_gprs' => ['nullable','numeric','min:0'],
            'valor_satelite' => ['nullable','numeric','min:0'],
            'valor_camera' => ['nullable','numeric','min:0'],
            'valor_radio' => ['nullable','numeric','min:0'],
            'valor_rfid' => ['nullable','numeric','min:0'],
            'valor_can' => ['nullable','numeric','min:0'],
            'valor_video' => ['nullable','numeric','min:0'],
            'observacoes' => ['nullable','string','max:3000'],
        ]);

        $prices = [
            'GPRS' => (float) ($data['valor_gprs'] ?? 0),
            'SATELITE' => (float) ($data['valor_satelite'] ?? 0),
            'CAMERA' => (float) ($data['valor_camera'] ?? 0),
            'RADIO' => (float) ($data['valor_radio'] ?? 0),
            'RFID' => (float) ($data['valor_rfid'] ?? 0),
            'CAN' => (float) ($data['valor_can'] ?? 0),
            'VIDEO' => (float) ($data['valor_video'] ?? 0),
        ];

        $termDate = Carbon::parse($data['ultima_atualizacao_termo'])->startOfDay();
        $expiry = $termDate->copy()->addMonthsNoOverflow((int) $data['prazo_contrato_meses']);

        $existing = ClientContract::query()->where('cliente', trim($data['cliente']))->first();
        $payload = [
            'cliente' => trim($data['cliente']),
            'ultima_atualizacao_termo' => $termDate->format('Y-m-d'),
            'prazo_contrato_meses' => (int) $data['prazo_contrato_meses'],
            'vencimento_contrato' => $expiry->format('Y-m-d'),
            'precos_por_tipo' => $prices,
            'prices' => $prices,
            'observacoes' => $data['observacoes'] ?? '',
            'atualizado_em' => now()->format('Y-m-d H:i:s'),
            'updated_by' => $request->user()->username ?? $request->user()->email,
        ];
        if ($existing) {
            $existing->fill($payload)->save();
        } else {
            ClientContract::query()->create($payload);
        }

        $logger->log('Contrato de cliente atualizado', ['cliente' => $data['cliente']]);
        return back()->with('success', 'Contrato salvo.');
    }

    public function partners(): View
    {
        $records = PartnerTerminal::query()->orderBy('parceiro', 'asc')->limit(1500)->get();
        $groups = $records->groupBy('parceiro')->map(fn ($items, $partner) => [
            'partner' => $partner ?: 'Sem parceiro',
            'terminals' => $items->count(),
            'total' => round((float) $items->sum(fn ($x) => (float) ($x->valor ?? $x->valor_faturado ?? 0)), 2),
        ])->values();

        return view('finance.partners', compact('records', 'groups'));
    }

    public function commissions(): View
    {
        $proposals = \App\Models\Proposal::query()->where('status', 'approved')->orderBy('data_geracao', 'desc')->limit(2000)->get();
        $rows = $proposals->groupBy('consultor')->map(fn ($items, $consultant) => [
            'consultant' => $consultant ?: 'Não informado',
            'count' => $items->count(),
            'revenue' => round((float) $items->sum('valor_total'), 2),
        ])->values();

        return view('finance.commissions', compact('rows'));
    }

    public function sugesp(): View
    {
        $metrics = BillingMonthlyMetric::query()->orderBy('period_key', 'desc')->orderBy('cliente', 'asc')->limit(3000)->get();
        return view('finance.sugesp', compact('metrics'));
    }
}
