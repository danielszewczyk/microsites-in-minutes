<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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
     * Billing is stubbed: the monetization data model exists (plans, billing
     * modes, Stripe id columns on owners) but nothing charges. These keys are
     * TEST keys only and 'live' must stay false until Daniel decides to charge.
     */
    'stripe' => [
        'enabled' => (bool) env('STRIPE_ENABLED', false),
        'live' => false,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
     * Bouncer (usebouncer.com) email-deliverability verification. When a key is
     * set, owners who turn it on get their entry emails verified; without a key
     * the NullVerifier is used and entries are marked "unverified".
     */
    'bouncer' => [
        'key' => env('BOUNCER_API_KEY'),
    ],

    /*
     * Cloudflare Turnstile bot protection for the public entry form and the
     * email-code endpoint. When both keys are set, a verified token is required
     * server-side before a code is sent or an entry is accepted; without them
     * the check is skipped so the form still works and activates on key set.
     */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
     * Default sender for verification emails. The platform's own Resend-verified
     * domain (micrositesinminutes.com). This is the DEFAULT only — Site can
     * override it per site once per-customer verified domains are wired, so this
     * address is never hard-coded at the call site.
     */
    'verification' => [
        'from_address' => env('VERIFICATION_FROM_ADDRESS', 'verify@micrositesinminutes.com'),
        'from_name' => env('VERIFICATION_FROM_NAME', 'Microsites in Minutes'),
    ],

];
