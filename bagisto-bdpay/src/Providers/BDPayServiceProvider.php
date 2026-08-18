<?php

namespace Webkul\BDPay\Providers;

use Illuminate\Support\ServiceProvider;

class BDPayServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'bdpay');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'bdpay');

        $this->publishes([
            __DIR__ . '/../Config/paymentmethods.php' => config_path('paymentmethods.php'),
        ], 'bdpay-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/paymentmethods.php',
            'payment_methods'
        );

        $this->mergeConfigFrom(
            dirname(__DIR__) . '/Config/system.php',
            'core'
        );
    }
}
