<?php

/**
 * ToanchetPay configuration — publish to config/toanchetpay.php in your Laravel app.
 *
 * php artisan vendor:publish --tag=toanchetpay-config
 */

return [

    /*
     | Merchant credentials — get these from ACLEDA Bank.
     */
    'merchant_id'   => env('TOANCHETPAY_MERCHANT_ID',   ''),
    'login_id'      => env('TOANCHETPAY_LOGIN_ID',       ''),
    'password'      => env('TOANCHETPAY_PASSWORD',       ''),
    'secret_key'    => env('TOANCHETPAY_SECRET_KEY',     ''),
    'merchant_name' => env('TOANCHETPAY_MERCHANT_NAME',  ''),

    /*
     | API base URL.
     | UAT:  https://epaymentuat.acledabank.com.kh
     | PROD: https://epayment.acledabank.com.kh  (confirm with ACLEDA)
     */
    'base_url'      => env('TOANCHETPAY_BASE_URL', 'https://epaymentuat.acledabank.com.kh'),

    /*
     | HTTP settings.
     */
    'timeout'    => env('TOANCHETPAY_TIMEOUT',    30),
    'verify_ssl' => env('TOANCHETPAY_VERIFY_SSL', true),

];
