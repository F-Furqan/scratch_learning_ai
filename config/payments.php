<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'paddle'),

    'paddle' => [
        'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),
        'api_key' => env('PADDLE_API_KEY'),
        'sandbox_api_url' => env('PADDLE_SANDBOX_API_URL', 'https://sandbox-api.paddle.com'),
        'live_api_url' => env('PADDLE_LIVE_API_URL', 'https://api.paddle.com'),
        'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('PADDLE_WEBHOOK_TOLERANCE_SECONDS', 5),
        'checkout_success_url' => env('PADDLE_CHECKOUT_SUCCESS_URL', env('APP_URL').'/student/dashboard?checkout=success'),
        'checkout_cancel_url' => env('PADDLE_CHECKOUT_CANCEL_URL', env('APP_URL').'/courses?checkout=cancelled'),
    ],
];
