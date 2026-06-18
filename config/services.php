<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // ── VNPay Sandbox ───────────────────────────────────────────────────────
    'vnpay' => [
        'tmn_code'             => env('VNP_TMN_CODE'),
        'hash_secret'          => env('VNP_HASH_SECRET'),
        'url'                  => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url'           => env('VNP_RETURN_URL'),
        'ipn_url'              => env('VNP_IPN_URL'),
        'version'              => env('VNP_VERSION', '2.1.0'),
        'command'              => env('VNP_COMMAND', 'pay'),
        'currency_code'        => env('VNP_CURRENCY_CODE', 'VND'),
        'locale'               => env('VNP_LOCALE', 'vn'),
        'order_type'           => env('VNP_ORDER_TYPE', 'other'),
        'frontend_return_url'  => env('VNP_FRONTEND_RETURN_URL', 'http://localhost:5173/thanh-toan-thanh-cong'),
    ],

];
