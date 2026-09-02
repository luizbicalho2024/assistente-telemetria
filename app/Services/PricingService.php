<?php

namespace App\Services;

use App\Models\PricingConfig;

class PricingService
{
    public const MIN_CUSTOM_MARGIN_PERCENT = 30.0;

    public function defaults(): array
    {
        $plans = [
            '12 Meses' => [
                'GPRS / Gsm' => 80.88,
                'Satélite' => 193.80,
                'Identificador de Motorista / RFID' => 19.25,
                'Leitor de Rede CAN / Telemetria' => 75.25,
                'Videomonitoramento + DMS + ADAS' => 409.11,
            ],
            '24 Meses' => [
                'GPRS / Gsm' => 53.92,
                'Satélite' => 129.20,
                'Identificador de Motorista / RFID' => 12.83,
                'Leitor de Rede CAN / Telemetria' => 50.17,
                'Videomonitoramento + DMS + ADAS' => 272.74,
            ],
            '36 Meses' => [
                'GPRS / Gsm' => 44.93,
                'Satélite' => 107.67,
                'Identificador de Motorista / RFID' => 10.69,
                'Leitor de Rede CAN / Telemetria' => 41.81,
                'Videomonitoramento + DMS + ADAS' => 227.28,
            ],
        ];

        $products = array_values(array_unique(array_merge(...array_map('array_keys', array_values($plans)))));
        sort($products);

        return [
            '_id' => 'global_prices',
            'PLANOS_PJ' => $plans,
            'CUSTOS_PJ' => array_map(
                fn (array $items) => array_fill_keys(array_keys($items), 0.0),
                $plans
            ),
            'CUSTOS_DETALHADOS_PJ' => array_fill_keys($products, []),
            'INSTALACAO_PJ' => array_fill_keys($products, ['preco_venda' => 0.0, 'custo' => 0.0]),
            'CUSTO_FIXO_IMPLANTACAO_PJ' => 0.0,
            'MARGEM_MINIMA_PERSONALIZADA_PJ' => 30.0,
            'CENARIOS_QUANTIDADE_PJ' => [1, 5, 10, 25, 50, 100, 200],
            'PRODUTOS_PJ_DESCRICAO' => [
                'GPRS / Gsm' => 'Equipamento de rastreamento GSM/GPRS 2G ou 4G.',
                'Satélite' => 'Equipamento de rastreamento via satélite para cobertura total.',
                'Identificador de Motorista / RFID' => 'Identificação automática de motoristas via RFID.',
                'Leitor de Rede CAN / Telemetria' => 'Leitura de dados avançados de telemetria via rede CAN do veículo.',
                'Videomonitoramento + DMS + ADAS' => 'Videomonitoramento com câmeras, alertas de fadiga e assistência ao motorista.',
            ],
            'PRECOS_PF' => [
                'GPRS / Gsm' => 970.56,
                'Satelital' => 2325.60,
            ],
            'TAXAS_PARCELAMENTO_PF' => [
                '2' => 0.05, '3' => 0.065, '4' => 0.08, '5' => 0.09, '6' => 0.10,
                '7' => 0.11, '8' => 0.12, '9' => 0.13, '10' => 0.15, '11' => 0.16, '12' => 0.18,
            ],
            'PRECO_CUSTO_LICITACAO' => [
                'Rastreador GPRS/GSM 2G' => 300.00,
                'Rastreador GPRS/GSM 4G' => 400.00,
                'Rastreador Satelital' => 900.00,
                'Telemetria/CAN' => 600.00,
                'RFID - ID Motorista' => 153.00,
            ],
            'AMORTIZACAO_HARDWARE_MESES' => 12,
        ];
    }

    public function config(): array
    {
        $record = PricingConfig::query()->where('_id', 'global_prices')->first();
        $data = $record?->toArray() ?? [];
        return $this->normalize(array_merge($this->defaults(), $data));
    }

    public function normalize(array $data): array
    {
        $data['MARGEM_MINIMA_PERSONALIZADA_PJ'] = max(
            self::MIN_CUSTOM_MARGIN_PERCENT,
            min(99.0, (float) ($data['MARGEM_MINIMA_PERSONALIZADA_PJ'] ?? 30))
        );
        $data['CUSTO_FIXO_IMPLANTACAO_PJ'] = max(0.0, (float) ($data['CUSTO_FIXO_IMPLANTACAO_PJ'] ?? 0));
        $data['AMORTIZACAO_HARDWARE_MESES'] = max(1, (int) ($data['AMORTIZACAO_HARDWARE_MESES'] ?? 12));
        return $data;
    }

    public function money(float|int|string|null $value): float
    {
        return round((float) ($value ?? 0), 2, PHP_ROUND_HALF_UP);
    }

    public function grossMarginValue(float $salePrice, float $cost): float
    {
        return $this->money($salePrice - $cost);
    }

    public function grossMarginPercent(float $salePrice, float $cost): ?float
    {
        if (abs($salePrice) < 0.0000001) {
            return null;
        }
        return round((($salePrice - $cost) / $salePrice) * 100, 2, PHP_ROUND_HALF_UP);
    }

    public function salePriceFromMargin(float $cost, float $targetMarginPercent): float
    {
        if ($cost < 0) {
            throw new \InvalidArgumentException('O custo não pode ser negativo.');
        }
        if ($targetMarginPercent >= 100) {
            throw new \InvalidArgumentException('A margem alvo deve ser inferior a 100%.');
        }

        $denominator = 1 - ($targetMarginPercent / 100);
        if ($denominator <= 0) {
            throw new \InvalidArgumentException('A margem alvo gera um preço matematicamente inválido.');
        }
        return $this->money($cost / $denominator);
    }

    public function summarizeCostComponents(array $components, int $months): array
    {
        $months = max(1, $months);
        $recurring = 0.0;
        $oneTime = 0.0;

        foreach ($components as $component) {
            $value = $this->money($component['valor'] ?? $component['Valor'] ?? 0);
            $incidence = trim((string) ($component['incidencia'] ?? $component['Incidência'] ?? 'Mensal por veículo'));
            if ($incidence === 'Único por veículo') {
                $oneTime += $value;
            } else {
                $recurring += $value;
            }
        }

        return [
            'recurring_monthly' => $this->money($recurring),
            'one_time_per_vehicle' => $this->money($oneTime),
            'monthly_equivalent' => $this->money($recurring + ($oneTime / $months)),
        ];
    }

    public function proposalTotals(
        float $recurringSalePerVehicle,
        float $recurringCostPerVehicle,
        int $months,
        int $vehicles,
        float $installationSalePerVehicle = 0,
        float $installationCostPerVehicle = 0,
        float $oneTimeCostPerVehicle = 0,
        bool $chargeInstallation = true,
        float $fixedCost = 0,
    ): array {
        $vehicles = max(1, $vehicles);
        $months = max(1, $months);

        $recurringRevenue = $this->money($recurringSalePerVehicle * $months * $vehicles);
        $recurringCost = $this->money($recurringCostPerVehicle * $months * $vehicles);
        $installationRevenue = $chargeInstallation ? $this->money($installationSalePerVehicle * $vehicles) : 0.0;
        $installationCost = $this->money($installationCostPerVehicle * $vehicles);
        $oneTimeCost = $this->money($oneTimeCostPerVehicle * $vehicles);
        $fixedCost = $this->money($fixedCost);

        $totalRevenue = $this->money($recurringRevenue + $installationRevenue);
        $totalCost = $this->money($recurringCost + $oneTimeCost + $installationCost + $fixedCost);
        $monthlyRevenue = $this->money($recurringSalePerVehicle * $vehicles);
        $monthlyCost = $this->money($recurringCostPerVehicle * $vehicles);
        $monthlyMargin = $this->money($monthlyRevenue - $monthlyCost);
        $installationSubsidy = $chargeInstallation ? 0.0 : $installationCost;
        $payback = (!$chargeInstallation && $installationSubsidy > 0 && $monthlyMargin > 0)
            ? round($installationSubsidy / $monthlyMargin, 2, PHP_ROUND_HALF_UP)
            : null;

        return [
            'vehicles' => $vehicles,
            'months' => $months,
            'monthly_revenue' => $monthlyRevenue,
            'monthly_cost' => $monthlyCost,
            'monthly_margin' => $monthlyMargin,
            'recurring_revenue' => $recurringRevenue,
            'recurring_cost' => $recurringCost,
            'installation_revenue' => $installationRevenue,
            'installation_cost' => $installationCost,
            'one_time_cost' => $oneTimeCost,
            'fixed_cost' => $fixedCost,
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_margin' => $this->money($totalRevenue - $totalCost),
            'margin_percent' => $this->grossMarginPercent($totalRevenue, $totalCost),
            'payback_months' => $payback,
        ];
    }

    public function mixedProposalTotals(array $items, int $months, bool $chargeInstallation = true, float $fixedCost = 0): array
    {
        $months = max(1, $months);
        $monthlyRevenue = $monthlyCost = $oneTimeCost = $installationRevenue = $installationCost = 0.0;
        $deviceQuantity = 0;

        foreach ($items as $item) {
            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($quantity < 1) {
                continue;
            }
            $deviceQuantity += $quantity;
            $monthlyRevenue += ((float) ($item['recurring_sale'] ?? 0)) * $quantity;
            $monthlyCost += ((float) ($item['recurring_cost'] ?? 0)) * $quantity;
            $oneTimeCost += ((float) ($item['one_time_cost'] ?? 0)) * $quantity;
            $installationCost += ((float) ($item['installation_cost'] ?? 0)) * $quantity;
            if ($chargeInstallation) {
                $installationRevenue += ((float) ($item['installation_sale'] ?? 0)) * $quantity;
            }
        }

        $monthlyRevenue = $this->money($monthlyRevenue);
        $monthlyCost = $this->money($monthlyCost);
        $recurringRevenue = $this->money($monthlyRevenue * $months);
        $recurringCost = $this->money($monthlyCost * $months);
        $oneTimeCost = $this->money($oneTimeCost);
        $installationRevenue = $this->money($installationRevenue);
        $installationCost = $this->money($installationCost);
        $fixedCost = $this->money($fixedCost);
        $totalRevenue = $this->money($recurringRevenue + $installationRevenue);
        $totalCost = $this->money($recurringCost + $oneTimeCost + $installationCost + $fixedCost);
        $monthlyMargin = $this->money($monthlyRevenue - $monthlyCost);

        return [
            'device_quantity' => $deviceQuantity,
            'months' => $months,
            'monthly_revenue' => $monthlyRevenue,
            'monthly_cost' => $monthlyCost,
            'monthly_margin' => $monthlyMargin,
            'recurring_revenue' => $recurringRevenue,
            'recurring_cost' => $recurringCost,
            'installation_revenue' => $installationRevenue,
            'installation_cost' => $installationCost,
            'one_time_cost' => $oneTimeCost,
            'fixed_cost' => $fixedCost,
            'total_revenue' => $totalRevenue,
            'total_cost' => $totalCost,
            'total_margin' => $this->money($totalRevenue - $totalCost),
            'margin_percent' => $this->grossMarginPercent($totalRevenue, $totalCost),
            'payback_months' => (!$chargeInstallation && $installationCost > 0 && $monthlyMargin > 0)
                ? round($installationCost / $monthlyMargin, 2, PHP_ROUND_HALF_UP)
                : null,
        ];
    }

    public function breakEvenVehicleCount(array $items, int $baseFleetVehicles, int $months, bool $chargeInstallation, float $fixedCost, float $targetMarginPercent = 30): ?int
    {
        $baseFleetVehicles = max(1, $baseFleetVehicles);
        $variable = $this->mixedProposalTotals($items, $months, $chargeInstallation, 0);
        $revenuePerVehicle = $variable['total_revenue'] / $baseFleetVehicles;
        $costPerVehicle = $variable['total_cost'] / $baseFleetVehicles;
        if ($revenuePerVehicle <= 0) {
            return null;
        }

        $target = $targetMarginPercent / 100;
        $contribution = $revenuePerVehicle * (1 - $target) - $costPerVehicle;
        if ($contribution <= 0) {
            return null;
        }

        return max(1, (int) ceil(max(0, $fixedCost) / $contribution));
    }
}
