<?php

namespace App\Http\Controllers;

use App\Models\BillingMonthlyMetric;
use App\Models\InventoryItem;
use App\Models\Proposal;
use App\Models\Tracker;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $latestPeriod = BillingMonthlyMetric::query()->orderBy('period_key', 'desc')->value('period_key');
        $monthly = $latestPeriod
            ? BillingMonthlyMetric::query()->where('period_key', $latestPeriod)->get()
            : collect();

        return view('dashboard', [
            'proposalCount' => Proposal::query()->count(),
            'pendingProposals' => Proposal::query()->where('status', 'pending_approval')->count(),
            'userCount' => User::query()->count(),
            'trackerCount' => max(Tracker::query()->count(), InventoryItem::query()->count()),
            'latestPeriod' => $latestPeriod,
            'billingRevenue' => (float) $monthly->sum('receita'),
            'billingVehicles' => (int) $monthly->sum('veiculos_faturados'),
            'billingClients' => $monthly->count(),
            'recentProposals' => Proposal::query()->orderBy('data_geracao', 'desc')->limit(8)->get(),
        ]);
    }
}
