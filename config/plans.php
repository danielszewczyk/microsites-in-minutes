<?php

/*
 * Monetization data model for Microsites in Minutes. Pricing itself is not the
 * focus and the numbers here are placeholders; what matters is that the app
 * understands tiers, limits and billing modes from the start so the schema and
 * the enforcement points exist. No money is ever charged — see App\Sites\Plans
 * and the Stripe stub in config/services.php.
 */
return [

    'default' => 'free',

    /*
     * The ways a site can be billed. Only the data model is built; none of
     * these actually charge. "subscription" is a recurring plan, "per_campaign"
     * is a one-off charge for a single promo, "metered_entries" bills by the
     * number of entries captured.
     */
    'billing_modes' => ['subscription', 'per_campaign', 'metered_entries'],

    'tiers' => [

        'free' => [
            'name' => 'Free',
            'price' => 0,
            'price_label' => 'Free',
            'max_sites' => 1,
            'monthly_entries' => 100,
            'monthly_views' => 2_000,
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
            'monthly_entries' => 5_000,
            'monthly_views' => 100_000,
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
            'monthly_entries' => 100_000,
            'monthly_views' => 2_000_000,
            'custom_domain' => true,
            'remove_branding' => true,
            'analytics' => true,
            'integrations' => true,
        ],

    ],

];
