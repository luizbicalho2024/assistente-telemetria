<?php

use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\SimulatorController;
use App\Http\Controllers\TrackerController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('login.perform');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->middleware('permission:dashboard')->name('dashboard');

    Route::middleware('permission:simulador.pj')->group(function () {
        Route::get('/simuladores/pj', [SimulatorController::class, 'pj'])->name('simulator.pj');
        Route::post('/simuladores/pj', [SimulatorController::class, 'calculatePj'])->name('simulator.pj.calculate');
    });
    Route::middleware('permission:simulador.pf')->group(function () {
        Route::get('/simuladores/pf', [SimulatorController::class, 'pf'])->name('simulator.pf');
        Route::post('/simuladores/pf', [SimulatorController::class, 'calculatePf'])->name('simulator.pf.calculate');
    });
    Route::middleware('permission:simulador.licitacao')->group(function () {
        Route::get('/simuladores/licitacao', [SimulatorController::class, 'licitacao'])->name('simulator.licitacao');
        Route::post('/simuladores/licitacao', [SimulatorController::class, 'calculateLicitacao'])->name('simulator.licitacao.calculate');
    });

    Route::get('/propostas', [ProposalController::class, 'index'])->middleware('permission:propostas.dashboard')->name('proposals.index');
    Route::get('/aprovacoes', [ProposalController::class, 'approvals'])->middleware('permission:aprovacoes')->name('proposals.approvals');
    Route::post('/aprovacoes/{id}/aprovar', [ProposalController::class, 'approve'])->middleware('permission:aprovacoes')->name('proposals.approve');
    Route::post('/aprovacoes/{id}/rejeitar', [ProposalController::class, 'reject'])->middleware('permission:aprovacoes')->name('proposals.reject');

    Route::get('/churn', [ModuleController::class, 'churn'])->middleware('permission:churn')->name('module.churn');
    Route::get('/consultas', [ModuleController::class, 'consultas'])->middleware('permission:consultas')->name('module.consultas');
    Route::get('/jornada', [ModuleController::class, 'jornada'])->middleware('permission:jornada')->name('module.jornada');
    Route::get('/mercado', [ModuleController::class, 'mercado'])->middleware('permission:mercado')->name('module.mercado');
    Route::post('/mercado', [ModuleController::class, 'storeMarket'])->middleware('permission:mercado')->name('module.mercado.store');
    Route::get('/clientes', [ModuleController::class, 'clientes'])->middleware('permission:clientes')->name('module.clientes');
    Route::post('/clientes', [ModuleController::class, 'storeCustomer'])->middleware('permission:clientes')->name('module.clientes.store');
    Route::get('/terminais', [ModuleController::class, 'terminais'])->middleware('permission:terminais')->name('module.terminais');

    Route::middleware('permission:estoque')->group(function () {
        Route::get('/estoque', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/estoque/importar', [InventoryController::class, 'import'])
            ->middleware('throttle:6,1')
            ->name('inventory.import');
    });

    Route::middleware('permission:comandos')->group(function () {
        Route::get('/comandos-rastreadores', [TrackerController::class, 'commands'])->name('trackers.commands');
        Route::post('/comandos-rastreadores', [TrackerController::class, 'generate'])
            ->middleware('throttle:30,1')
            ->name('trackers.generate');
        Route::post('/comandos-rastreadores/sms', [TrackerController::class, 'sendSms'])
            ->middleware('throttle:20,1')
            ->name('trackers.sms');
    });

    Route::get('/financeiro/sugesp', [FinanceController::class, 'sugesp'])->middleware('permission:financeiro.sugesp')->name('finance.sugesp');
    Route::middleware('permission:financeiro.faturamento')->group(function () {
        Route::get('/financeiro/faturamento', [FinanceController::class, 'billing'])->name('finance.billing');
        Route::post('/financeiro/faturamento/processar', [FinanceController::class, 'processBilling'])
            ->middleware('throttle:4,1')
            ->name('finance.billing.process');
        Route::post('/financeiro/faturamento/fechar', [FinanceController::class, 'closeBilling'])->name('finance.billing.close');
    });
    Route::get('/financeiro/parceiros', [FinanceController::class, 'partners'])->middleware('permission:financeiro.parceiros')->name('finance.partners');
    Route::get('/financeiro/resumo', [FinanceController::class, 'summary'])->middleware('permission:financeiro.resumo')->name('finance.summary');
    Route::middleware('permission:financeiro.contratos')->group(function () {
        Route::get('/financeiro/contratos', [FinanceController::class, 'contracts'])->name('finance.contracts');
        Route::post('/financeiro/contratos', [FinanceController::class, 'storeContract'])->name('finance.contracts.store');
    });
    Route::get('/financeiro/historico', [FinanceController::class, 'history'])->middleware('permission:financeiro.historico')->name('finance.history');
    Route::get('/financeiro/comissoes', [FinanceController::class, 'commissions'])->middleware('permission:financeiro.comissoes')->name('finance.commissions');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:admin.users')->group(function () {
            Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
            Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
            Route::put('/usuarios/{id}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        Route::middleware('permission:admin.roles')->group(function () {
            Route::get('/perfis', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/perfis', [RoleController::class, 'store'])->name('roles.store');
        });

        Route::middleware('permission:admin.settings')->group(function () {
            Route::get('/configuracoes', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/configuracoes/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding');
            Route::put('/configuracoes/precos', [SettingsController::class, 'updatePricing'])->name('settings.pricing');
        });

        Route::get('/logs', [LogController::class, 'index'])->middleware('permission:admin.logs')->name('logs.index');
    });

    Route::view('/ajuda', 'help')->name('help');
});
