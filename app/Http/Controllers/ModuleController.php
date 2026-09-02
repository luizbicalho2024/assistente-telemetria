<?php

namespace App\Http\Controllers;

use App\Models\BillingMonthlyMetric;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\MarketResearch;
use App\Models\Proposal;
use App\Models\Tracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function churn(): View
    {
        $metrics = BillingMonthlyMetric::query()->orderBy('period_key', 'asc')->get();
        $periods = $metrics->groupBy('period_key')->map(function ($rows, $period) {
            return [
                'period' => $period,
                'revenue' => round((float) $rows->sum('receita'), 2),
                'vehicles' => (int) $rows->sum('veiculos_ativos_fim_mes'),
                'activations' => (int) $rows->sum('ativacoes'),
                'deactivations' => (int) $rows->sum('desativacoes'),
                'clients' => $rows->count(),
            ];
        })->values();

        return view('modules.churn', compact('periods'));
    }

    public function consultas(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $trackers = collect();
        $customers = collect();

        if ($term !== '') {
            $trackers = Tracker::query()
                ->where(function ($q) use ($term) {
                    $q->where('Terminal', 'like', "%{$term}%")
                        ->orWhere('Nº Equipamento', 'like', "%{$term}%")
                        ->orWhere('Modelo', 'like', "%{$term}%");
                })->limit(100)->get();

            $customers = Customer::query()
                ->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('cliente', 'like', "%{$term}%")
                        ->orWhere('document', 'like', "%{$term}%");
                })->limit(100)->get();
        }

        return view('modules.consultas', compact('term', 'trackers', 'customers'));
    }

    public function jornada(): View
    {
        return view('modules.placeholder', [
            'title' => 'Análise de jornada',
            'subtitle' => 'Área unificada para importar e analisar jornadas, tempos e eventos operacionais.',
            'notes' => [
                'A estrutura Laravel está preparada para persistir novas análises no MongoDB.',
                'As consultas históricas importadas do projeto antigo permanecem disponíveis nas coleções migradas.',
            ],
        ]);
    }

    public function mercado(): View
    {
        return view('modules.market', [
            'records' => MarketResearch::query()->orderBy('created_at', 'desc')->limit(300)->get(),
        ]);
    }

    public function storeMarket(Request $request)
    {
        $data = $request->validate([
            'empresa' => ['required','string','max:180'],
            'produto' => ['required','string','max:180'],
            'preco' => ['nullable','numeric','min:0'],
            'observacoes' => ['nullable','string','max:3000'],
        ]);
        MarketResearch::query()->create($data + ['created_by' => $request->user()->username ?? $request->user()->email]);
        return back()->with('success', 'Pesquisa registrada.');
    }

    public function clientes(Request $request): View
    {
        $records = Customer::query()->orderBy('name', 'asc')->limit(500)->get();
        return view('modules.customers', compact('records'));
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:180'],
            'document' => ['nullable','string','max:40'],
            'email' => ['nullable','email','max:180'],
            'phone' => ['nullable','string','max:40'],
            'notes' => ['nullable','string','max:3000'],
        ]);
        Customer::query()->create($data);
        return back()->with('success', 'Cliente salvo.');
    }

    public function terminais(): View
    {
        $trackers = Tracker::query()->orderBy('Modelo', 'asc')->limit(1000)->get();
        return view('modules.terminals', compact('trackers'));
    }
}
