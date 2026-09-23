<?php

$demoAccounts = json_decode((string) env('HIMS_DEMO_ACCOUNTS_JSON', '[]'), true);

return [
    'super_admin' => [
        'name' => env('HIMS_SUPER_ADMIN_NAME'),
        'email' => env('HIMS_SUPER_ADMIN_EMAIL'),
        'phone' => env('HIMS_SUPER_ADMIN_PHONE'),
        'department' => env('HIMS_SUPER_ADMIN_DEPARTMENT'),
        'password' => env('HIMS_SUPER_ADMIN_PASSWORD'),
    ],

    'owner_admin' => [
        'name' => env('HIMS_OWNER_ADMIN_NAME'),
        'email' => env('HIMS_OWNER_ADMIN_EMAIL'),
        'phone' => env('HIMS_OWNER_ADMIN_PHONE'),
        'department' => env('HIMS_OWNER_ADMIN_DEPARTMENT'),
        'password' => env('HIMS_OWNER_ADMIN_PASSWORD'),
    ],

    'demo_accounts' => is_array($demoAccounts) ? $demoAccounts : [],
];
