<?php

declare(strict_types=1);

namespace App\Providers;

use ToanchetPay\Client\ToanchetPayClient;
use ToanchetPay\Config\ToanchetPayConfig;
use ToanchetPay\Helpers\PaymentForm;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel Service Provider — registers ToanchetPayClient as a singleton.
 *
 * Register in config/app.php providers[]:
 *   App\Providers\ToanchetPayServiceProvider::class,
 *
 * Config in config/toanchetpay.php (see below) or .env:
 *   TOANCHETPAY_MERCHANT_ID=...
 *   TOANCHETPAY_LOGIN_ID=...
 *   TOANCHETPAY_PASSWORD=...
 *   TOANCHETPAY_SECRET_KEY=...
 *   TOANCHETPAY_MERCHANT_NAME=MERCHANTNAME
 *   TOANCHETPAY_BASE_URL=https://epaymentuat.acledabank.com.kh
 */
class ToanchetPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToanchetPayConfig::class, function () {
            return new ToanchetPayConfig(
                merchantId:   config('toanchetpay.merchant_id',   env('TOANCHETPAY_MERCHANT_ID')),
                loginId:      config('toanchetpay.login_id',      env('TOANCHETPAY_LOGIN_ID')),
                password:     config('toanchetpay.password',      env('TOANCHETPAY_PASSWORD')),
                secretKey:    config('toanchetpay.secret_key',    env('TOANCHETPAY_SECRET_KEY')),
                merchantName: config('toanchetpay.merchant_name', env('TOANCHETPAY_MERCHANT_NAME')),
                baseUrl:      config('toanchetpay.base_url',      env('TOANCHETPAY_BASE_URL', 'https://epaymentuat.acledabank.com.kh')),
                timeout:      (int) config('toanchetpay.timeout', 30),
                verifySsl:    (bool) config('toanchetpay.verify_ssl', true)
            );
        });

        $this->app->singleton(ToanchetPayClient::class, function ($app) {
            return new ToanchetPayClient($app->make(ToanchetPayConfig::class));
        });

        $this->app->singleton(PaymentForm::class, function ($app) {
            return new PaymentForm($app->make(ToanchetPayConfig::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/toanchetpay.php' => config_path('toanchetpay.php'),
        ], 'toanchetpay-config');
    }
}
