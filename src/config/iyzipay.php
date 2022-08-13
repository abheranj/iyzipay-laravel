<?php

if(strtolower(env('IYZIPAY_PAYMENT_MODE'))=='live'){

    return [
        'baseUrl' => env('LIVE_IYZIPAY_BASE_URL', 'https://sandbox-api.iyzipay.com'),
        'apiKey' => env('LIVE_IYZIPAY_API_KEY', ''),
        'secretKey' => env('LIVE_IYZIPAY_SECRET_KEY', ''),
    ];
} else {
    return [
        'baseUrl' => env('SANDBOX_IYZIPAY_BASE_URL', 'https://sandbox-api.iyzipay.com'),
        'apiKey' => env('SANDBOX_IYZIPAY_API_KEY', ''),
        'secretKey' => env('SANDBOX_IYZIPAY_SECRET_KEY', ''),
    ];
}