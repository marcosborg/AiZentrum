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
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'moloni' => [
        'client_id' => env('MOLONI_CLIENT_ID', 'airbagszentrum'),
        'client_secret' => env('MOLONI_CLIENT_SECRET', 'e70b951265cca18575fef98831e5007b2da2fcdf'),
        'username' => env('MOLONI_USERNAME', 'nadinemoreira@zentrum-group.com'),
        'password' => env('MOLONI_PASSWORD', 'Brun@2008*'),
        'company_id' => env('MOLONI_COMPANY_ID', '13968'),
        'api_url' => env('MOLONI_API_URL', 'https://api.moloni.pt/v1'),
        'document_set_id' => env('MOLONI_DOCUMENT_SET_ID', '784358'),
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],
    'ps' => [
        'url' => env('PS_API_URL', 'https://techniczentrum.com/api'),
        'key' => env('PS_API_KEY', 'U7J6Y3I3N4D6W8V9T0P5A2R1E3S4X6C7'),
    ],
    'zcm' => [
        'base' => env('ZCM_API_BASE'),
        'token' => env('ZCM_API_TOKEN'),
        'manager_base' => env('ZCMANAGER_API_URL', 'https://zcmanager.com'),
        'manager_token' => env('ZCMANAGER_API_TOKEN'),
    ],
];
