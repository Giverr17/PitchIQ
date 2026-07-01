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
    'airtime' => [
        'live' => env('AIRTIME_LIVE', false),
        'base_url' => env('VTU_BASE_URL', 'https://vtu.ng/wp-json'),
        'username' => env('VTU_USERNAME'),
        'password' => env('VTU_PASSWORD'),
    ],
    'ai' => [
        // Primary provider: Gemini (Prism resolves the key from config/prism.php → GEMINI_API_KEY)
        'model' => env('AI_MODEL', 'gemini-2.5-flash-lite'),
        // Fallback provider: Groq (used when Gemini rate-limits or a transport error persists → GROQ_API_KEY)
        'fallback_model' => env('AI_FALLBACK_MODEL', 'llama-3.3-70b-versatile'),
    ],

];
