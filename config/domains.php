<?php

/*
 * Vanity (custom) domains. The product sells a domain + hosting on it for a flat
 * yearly price — undercutting the agency rate ($250 at DJA) at $25. Serving the
 * site on the domain is done with Cloudflare for SaaS custom hostnames in
 * production; buying is done through a reseller registrar API. Both the registrar
 * purchase and the Cloudflare hostname/SSL are deploy-time wiring that needs
 * Daniel's API keys and sign-off — until then `stub` is true and nothing is
 * bought or charged; the data model, the buy flow and the Host-based routing all
 * work locally against stubbed availability.
 */
return [

    'stub' => (bool) env('VANITY_STUB', true),

    'price_cents' => 2_500,  // what the owner pays per year
    'cost_cents' => 1_200,   // our registrar cost (stub); the rest is margin

    // Where a customer points their domain (a CNAME) in production — the
    // Cloudflare for SaaS fallback origin / custom-hostname target.
    'cname_target' => env('VANITY_CNAME_TARGET', 'connect.micrositesinminutes.com'),

    // Hosts a customer may never claim (our own domains), so a vanity domain
    // cannot take over the app's homepage. Compared after normalisation
    // (lower-case, no leading www).
    'reserved' => array_values(array_filter([
        'localhost',
        'micrositesinminutes.com',
        parse_url((string) env('APP_URL'), PHP_URL_HOST),
        env('VANITY_CNAME_TARGET', 'connect.micrositesinminutes.com'),
    ])),

];
