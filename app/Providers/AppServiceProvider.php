<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view): void {
            try {
                $branding = SystemSetting::branding();
            } catch (Throwable) {
                $branding = SystemSetting::defaultBranding();
            }
            $view->with('branding', $branding);
        });
    }
}
