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
        'client_id' => env('MOLONI_CLIENT_ID'),
        'client_secret' => env('MOLONI_CLIENT_SECRET'),
        'username' => env('MOLONI_USERNAME'),
        'password' => env('MOLONI_PASSWORD'),
        'company_id' => env('MOLONI_COMPANY_ID'),
        'api_url' => env('MOLONI_API_URL'),
        'document_set_id' => env('MOLONI_DOCUMENT_SET_ID', '784358'), // opcional, mas útil ter default
    ],



];
