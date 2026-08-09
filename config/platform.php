<?php

return [
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'creator_agreement' => [
        'version' => env('CREATOR_AGREEMENT_VERSION', '2026-07-19'),
    ],
];
