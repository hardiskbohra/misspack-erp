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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | E-invoice (IRN/QR)
    |--------------------------------------------------------------------------
    | `manual` (default): the payload is generated here, the office registers
    | on the GST portal/GSP itself and records the IRN/Ack/QR back. `http`:
    | POST the NIC-schema payload to a configured JSON endpoint (a GSP or an
    | in-house bridge) which answers the four artefacts. See
    | docs/e-invoice.md.
    */
    'einvoice' => [
        'provider' => env('EINVOICE_PROVIDER', 'manual'),
        'endpoint' => env('EINVOICE_ENDPOINT'),
        'token' => env('EINVOICE_TOKEN'),
        'timeout' => (int) env('EINVOICE_TIMEOUT', 30),
    ],

];
