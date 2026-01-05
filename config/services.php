<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_GEOCODING_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'zoho' => [
        'enabled' => env('ZOHO_ENABLED', false),
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'organization_id' => env('ZOHO_ORGANIZATION_ID'),
        'department_id' => env('ZOHO_DEPARTMENT_ID'),
        'desk_user_id' => env('ZOHO_DESK_USER_ID'),
        'item_id' => env('ZOHO_ITEM_ID'),
        'adjustment_account' => env('ZOHO_ITEM_ADJUSTMENT_ACCOUNT_ID'),
        'warehouse_id' => env('ZOHO_ITEM_WAREHOUSE_ID'),
        'should_send_email' => env('SEND_WELCOME_MAIL_FOR_ZOHO_USERS'),
    ],

    'razorpay' => [
        'key' => env('RAZOR_KEY'),
        'secret' => env('RAZOR_SECRET'),
    ],
'wani' => [
        'providers' => env('WANI_PROVIDERS_URL'),
        'app' => [
            'auth' => env('M_APP_PROVIDER_AUTH_URL'),
            'id' => env('M_APP_ID'),
            'version' => env('M_APP_VERSION'),
        ],
        'captive' => [
            'portal' => env('CAPTIVE_PORTAL_URL'),
            'pdoa_private_key' => env('PDOA_PRIVATE_KEY'),
            'pdoa_private_key_password' => env('PDOA_PRIVATE_KEY_PASSWORD'),
            'pdoa_registry_id' => env('PDOA_REGISTRY_ID'),
        ],
    ],
    'mod_configuration' => [     // # Configuration Module
        'base_url' => env('MOD_CONFIGURATION_JWT_URL'),
        'secret' => env('MOD_CONFIGURATION_JWT_SECRET'),
    ],

];
