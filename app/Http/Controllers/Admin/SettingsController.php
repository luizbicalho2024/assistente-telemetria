<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingConfig;
use App\Models\SystemSetting;
use App\Services\ActivityLogger;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(PricingService $pricing): View
    {
        return view('admin.settings.edit', [
            'settings' => SystemSetting::branding(),
            'pricing' => $pricing->config(),
        ]);
    }

    public function updateBranding(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['required','string','max:80'],
            'system_subtitle' => ['required','string','max:160'],
            'footer_text' => ['nullable','string','max:160'],
            'primary_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'surface_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'muted_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_background_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_text_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_hover_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_active_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable','image','max:2048'],
            'sidebar_logo' => ['nullable','image','max:2048'],
        ]);

        $existing = SystemSetting::query()->where('_id', 'global_branding')->first();
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $data['logo_base64'] = base64_encode((string) file_get_contents($file->getRealPath()));
            $data['logo_mime'] = $file->getMimeType() ?: 'image/png';
        }
        if ($request->hasFile('sidebar_logo')) {
            $file = $request->file('sidebar_logo');
            $data['sidebar_logo_base64'] = base64_encode((string) file_get_contents($file->getRealPath()));
            $data['sidebar_logo_mime'] = $file->getMimeType() ?: 'image/png';
        }

        if ($existing) {
            $existing->fill($data)->save();
        } else {
            SystemSetting::query()->create(array_merge(['_id' => 'global_branding'], $data));
        }

        $logger->log('Identidade visual atualizada');
        return back()->with('success', 'Identidade visual salva.');
    }

    public function updatePricing(Request $request, PricingService $pricing, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'minimum_margin' => ['required','numeric','min:30','max:99'],
            'fixed_cost' => ['required','numeric','min:0'],
            'amortization_months' => ['required','integer','min:1','max:120'],
            'plan_prices' => ['required','array'],
            'plan_prices.*.*' => ['nullable','numeric','min:0'],
            'plan_costs' => ['required','array'],
            'plan_costs.*.*' => ['nullable','numeric','min:0'],
            'installation_sale' => ['nullable','array'],
            'installation_sale.*' => ['nullable','numeric','min:0'],
            'installation_cost' => ['nullable','array'],
            'installation_cost.*' => ['nullable','numeric','min:0'],
            'pf_prices' => ['nullable','array'],
            'pf_prices.*' => ['nullable','numeric','min:0'],
            'auction_costs' => ['nullable','array'],
            'auction_costs.*' => ['nullable','numeric','min:0'],
            'detailed_costs_json' => ['nullable','json'],
        ]);

        $config = $pricing->config();
        foreach ($data['plan_prices'] as $plan => $products) {
            foreach ($products as $product => $value) {
                $config['PLANOS_PJ'][$plan][$product] = max(0, (float) $value);
            }
        }
        foreach ($data['plan_costs'] as $plan => $products) {
            foreach ($products as $product => $value) {
                $config['CUSTOS_PJ'][$plan][$product] = max(0, (float) $value);
            }
        }

        foreach ($data['installation_sale'] ?? [] as $product => $value) {
            $config['INSTALACAO_PJ'][$product]['preco_venda'] = max(0, (float) $value);
        }
        foreach ($data['installation_cost'] ?? [] as $product => $value) {
            $config['INSTALACAO_PJ'][$product]['custo'] = max(0, (float) $value);
        }

        foreach ($data['pf_prices'] ?? [] as $product => $value) {
            $config['PRECOS_PF'][$product] = max(0, (float) $value);
        }
        foreach ($data['auction_costs'] ?? [] as $product => $value) {
            $config['PRECO_CUSTO_LICITACAO'][$product] = max(0, (float) $value);
        }
        if (!empty($data['detailed_costs_json'])) {
            $decoded = json_decode($data['detailed_costs_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('Custos detalhados devem ser um objeto JSON.');
            }
            $config['CUSTOS_DETALHADOS_PJ'] = $decoded;
        }

        $config['MARGEM_MINIMA_PERSONALIZADA_PJ'] = max(30, (float) $data['minimum_margin']);
        $config['CUSTO_FIXO_IMPLANTACAO_PJ'] = max(0, (float) $data['fixed_cost']);
        $config['AMORTIZACAO_HARDWARE_MESES'] = max(1, (int) $data['amortization_months']);

        $existing = PricingConfig::query()->where('_id', 'global_prices')->first();
        if ($existing) {
            $existing->fill($config)->save();
        } else {
            PricingConfig::query()->create($config);
        }

        $logger->log('Configuração comercial atualizada');
        return back()->with('success', 'Preços e custos salvos.');
    }
}
