<?php

/*
 * Monetization data model for Microsites in Minutes. Pricing itself is not the
 * focus and the numbers here are placeholders; what matters is that the app
 * understands tiers, allowances, metering and billing modes from the start so
 * the schema and the enforcement/metering points exist. No money is ever
 * charged — see App\Sites\BuildMeter, App\Sites\UsageReport and the Stripe stub
 * in config/services.php.
 *
 * The product bills three metered dimensions, each with a free allowance per
 * tier and an overage rate beyond it:
 *   - build minutes  — the time it takes to generate a site ("in minutes").
 *                      The first minute is free on every tier.
 *   - traffic        — page views served (stands in for bandwidth).
 *   - storage        — uploaded assets and data.
 */
return [

    'default' => 'free',

    'billing_modes' => ['subscription', 'per_campaign', 'metered_build', 'metered_entries'],

    /*
     * Overage rates once a tier's free allowance is used up. Placeholders.
     */
    'metering' => [
        'build_cents_per_minute' => 50,     // $0.50 per build-minute beyond the free allowance
        'traffic_cents_per_1k_views' => 20, // $0.20 per 1,000 views beyond the allowance
        'storage_cents_per_gb_month' => 25, // $0.25 per GB-month beyond the allowance
    ],

    'tiers' => [

        'free' => [
            'name' => 'Free',
            'price' => 0,
            'price_label' => 'Free',
            'max_sites' => 1,
            'free_build_minutes' => 1,
            'monthly_views' => 2_000,
            'monthly_entries' => 100,
            'storage_mb' => 50,
            'custom_domain' => false,
            'remove_branding' => false,
            'analytics' => false,
            'integrations' => false,
        ],

        'pro' => [
            'name' => 'Pro',
            'price' => 29_00,
            'price_label' => '$29/mo',
            'max_sites' => 10,
            'free_build_minutes' => 30,
            'monthly_views' => 100_000,
            'monthly_entries' => 5_000,
            'storage_mb' => 2_000,
            'custom_domain' => true,
            'remove_branding' => true,
            'analytics' => true,
            'integrations' => false,
        ],

        'business' => [
            'name' => 'Business',
            'price' => 99_00,
            'price_label' => '$99/mo',
            'max_sites' => 100,
            'free_build_minutes' => 120,
            'monthly_views' => 2_000_000,
            'monthly_entries' => 100_000,
            'storage_mb' => 20_000,
            'custom_domain' => true,
            'remove_branding' => true,
            'analytics' => true,
            'integrations' => true,
        ],

    ],

];
