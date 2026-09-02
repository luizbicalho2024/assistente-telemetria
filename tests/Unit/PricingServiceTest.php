<?php

namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_sale_price_from_margin_uses_margin_over_sale_price(): void
    {
        $service = new PricingService();
        $this->assertSame(142.86, $service->salePriceFromMargin(100, 30));
    }

    public function test_installation_cost_is_counted_even_when_installation_is_free(): void
    {
        $service = new PricingService();
        $result = $service->proposalTotals(
            recurringSalePerVehicle: 100,
            recurringCostPerVehicle: 50,
            months: 12,
            vehicles: 10,
            installationSalePerVehicle: 200,
            installationCostPerVehicle: 100,
            oneTimeCostPerVehicle: 0,
            chargeInstallation: false,
            fixedCost: 0,
        );

        $this->assertSame(12000.0, $result['total_revenue']);
        $this->assertSame(7000.0, $result['total_cost']);
        $this->assertSame(2.0, $result['payback_months']);
    }

    public function test_default_minimum_margin_is_thirty_percent(): void
    {
        $this->assertSame(30.0, PricingService::MIN_CUSTOM_MARGIN_PERCENT);
    }
}
