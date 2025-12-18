<?php

return [

    'facebook' => [
        'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'graph_version'     => env('FACEBOOK_GRAPH_VERSION', 'v24.0'),
        'verify_token'     => env('FACEBOOK_VERIFY_TOKEN'),
    ],

    'whatsapp' => [
        'verify_token'     => env('WHATSAPP_VERIFY_TOKEN'),
        'access_token'     => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id'  => env('WHATSAPP_PHONE_NUMBER_ID'),
        'graph_version'    => env('WHATSAPP_GRAPH_VERSION', 'v24.0'),
    ],

    'ebay' => [
        'auth_token'    => env('EBAY_AUTH_TOKEN'),
        'site_id'       => env('EBAY_SITE_ID', 0),
        'compat_level'  => env('EBAY_COMPAT_LEVEL', 1200),
    ],

    'shopify' => [
        'base_url'    => env('SHOPIFY_BASE_URL'),
        'admin_token' => env('SHOPIFY_ADMIN_TOKEN', ''),
        'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),

        // oauth
        'client_id'     => env('SHOPIFY_CLIENT_ID'),
        'client_secret' => env('SHOPIFY_CLIENT_SECRET'),
        'redirect_uri'  => env('SHOPIFY_REDIRECT_URI'),
        'scopes'        => env('SHOPIFY_SCOPES', 'read_orders'),
    ],

    'walmart_imap' => [
        // tên account IMAP đã config trong config/imap.php của app chính
        'account' => env('WALMART_IMAP_ACCOUNT', 'walmart'),
    ],

    'walmart' => [
        'base_url' => env('WALMART_BASE_URL', 'https://marketplace.walmartapis.com'),
        'channel_type' => env('WALMART_CHANNEL_TYPE', '0'),
        'svc_name' => env('WALMART_SVC_NAME', 'YourApp'),
    ],
];
