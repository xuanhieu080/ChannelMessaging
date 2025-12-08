<?php

return [

    'facebook' => [
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'graph_version'     => env('FACEBOOK_GRAPH_VERSION', 'v18.0'),
        'verify_token'     => env('FACEBOOK_VERIFY_TOKEN'),
    ],

    'ebay' => [
        'auth_token'    => env('EBAY_AUTH_TOKEN'),
        'site_id'       => env('EBAY_SITE_ID', 0),
        'compat_level'  => env('EBAY_COMPAT_LEVEL', 1200),
    ],

    'shopify' => [
        'base_url'    => env('SHOPIFY_BASE_URL'),
        'admin_token' => env('SHOPIFY_ADMIN_TOKEN'),
        'api_version' => env('SHOPIFY_API_VERSION', '2024-10'),
    ],

    'walmart_imap' => [
        // tên account IMAP đã config trong config/imap.php của app chính
        'account' => env('WALMART_IMAP_ACCOUNT', 'walmart'),
    ],
];
