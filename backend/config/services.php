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
     * "Sign in with Google" — the frontend gets a one-time authorization
     * code from a custom button (Google's OAuth2 code-client popup flow)
     * and hands it to the backend, which exchanges it server-to-server for
     * an ID token (GoogleAuthorizationCodeExchanger) and verifies it
     * (GoogleIdTokenVerifier). client_secret never reaches the frontend —
     * same trust boundary as JWT_SECRET.
     */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    /*
     * A single platform-wide bot for admin/system notifications (subscription
     * events, new tenant signups, ...) — completely separate from the bot
     * each tenant connects in Settings to message their own customers.
     */
    'platform_telegram' => [
        'bot_token' => env('PLATFORM_TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('PLATFORM_TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('PLATFORM_TELEGRAM_WEBHOOK_SECRET'),
    ],

];
