<?php

namespace App\Sites;

use App\Models\Owner;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

/**
 * Pay-to-publish for a single site. A $1 day pass checks out through Stripe's
 * hosted page (Apple Pay on the phone, saved for one-tap extensions), and the
 * $25 monthly plan runs as a Stripe subscription. Paid time lives on the site;
 * the webhook handlers below are the only place it is extended by a payment.
 */
class Billing
{
    /** How many payment references a site remembers, so a redelivered webhook cannot pay twice. */
    private const int MAX_REFERENCES = 50;

    protected ?StripeClient $stripe = null;

    /**
     * Hosted Checkout for one day. The card is saved for later extensions.
     */
    public function dayPassCheckoutUrl(Site $site, Owner $owner, string $returnUrl): string
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $this->customerFor($owner),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => config('plans.day_pass.price'),
                    'product_data' => ['name' => "Keep \"{$site->inputs['business_name']}\" live — 1 day"],
                ],
            ]],
            'payment_intent_data' => [
                'setup_future_usage' => 'off_session',
                'metadata' => ['site_id' => (string) $site->id, 'kind' => 'day'],
            ],
            'metadata' => ['site_id' => (string) $site->id, 'kind' => 'day'],
            'success_url' => $returnUrl.'?paid=day',
            'cancel_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    /**
     * Hosted Checkout for the monthly plan, billed as a Stripe subscription.
     */
    public function monthlyCheckoutUrl(Site $site, Owner $owner, string $returnUrl): string
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->customerFor($owner),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => config('plans.monthly_basic.price'),
                    'recurring' => ['interval' => 'month'],
                    'product_data' => ['name' => "Keep \"{$site->inputs['business_name']}\" live — monthly"],
                ],
            ]],
            'subscription_data' => ['metadata' => ['site_id' => (string) $site->id]],
            'metadata' => ['site_id' => (string) $site->id, 'kind' => 'monthly'],
            'success_url' => $returnUrl.'?paid=monthly',
            'cancel_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    /**
     * One tap extension: charge the saved card a day off-session. Returns false
     * when no card is saved or the bank wants the owner to confirm, so the
     * owner is sent back to a Checkout page instead.
     */
    public function extendWithSavedCard(Site $site, Owner $owner): bool
    {
        if ($owner->stripe_customer_id === null || $owner->stripe_payment_method_id === null) {
            return false;
        }

        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => config('plans.day_pass.price'),
                'currency' => 'usd',
                'customer' => $owner->stripe_customer_id,
                'payment_method' => $owner->stripe_payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'metadata' => ['site_id' => (string) $site->id, 'kind' => 'day'],
            ]);
        } catch (CardException) {
            return false;
        }

        if ($intent->status !== 'succeeded') {
            return false;
        }

        return $this->recordPayment($site, 'pi:'.$intent->id, fn () => $this->addDays($site, config('plans.day_pass.days')));
    }

    /**
     * Checkout completed. A day pass adds a day and keeps the card; a
     * subscription adds the first month and remembers its id for renewals.
     *
     * @param  array<string, mixed>  $session
     */
    public function handleCheckoutCompleted(array $session): void
    {
        $siteId = $session['metadata']['site_id'] ?? null;
        $site = $siteId !== null ? Site::query()->find($siteId) : null;

        if ($site === null || ($session['payment_status'] ?? null) === 'unpaid') {
            return;
        }

        $owner = $site->owner;

        if (($session['mode'] ?? null) === 'subscription') {
            $this->recordPayment($site, 'cs:'.$session['id'], function () use ($site, $session): void {
                $this->addMonths($site, 1);
                $site->update(['settings' => [...$site->settings, 'stripe_subscription_id' => $session['subscription'] ?? null]]);
            });

            return;
        }

        $intentId = $session['payment_intent'] ?? null;

        $this->recordPayment($site, 'cs:'.$session['id'], function () use ($site, $owner, $intentId): void {
            $this->addDays($site, config('plans.day_pass.days'));

            if ($intentId !== null) {
                $intent = $this->client()->paymentIntents->retrieve($intentId);
                $owner->update(['stripe_payment_method_id' => $intent->payment_method]);
            }
        });
    }

    /**
     * A monthly renewal. The first invoice is already covered by Checkout, so
     * it is skipped here to avoid adding the month twice.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function handleInvoicePaid(array $invoice): void
    {
        if (($invoice['billing_reason'] ?? null) === 'subscription_create') {
            return;
        }

        $site = Site::query()->where('settings->stripe_subscription_id', $invoice['subscription'] ?? null)->first();

        if ($site === null) {
            return;
        }

        $this->recordPayment($site, 'in:'.$invoice['id'], fn () => $this->addMonths($site, 1));
    }

    /**
     * Stripe ended the subscription. Paid time already bought still runs out on
     * its own; only the renewal link is removed.
     *
     * @param  array<string, mixed>  $subscription
     */
    public function handleSubscriptionDeleted(array $subscription): void
    {
        $site = Site::query()->where('settings->stripe_subscription_id', $subscription['id'] ?? null)->first();

        if ($site !== null) {
            $site->update(['settings' => [...$site->settings, 'stripe_subscription_id' => null]]);
        }
    }

    /**
     * Runs the paid-time change once per payment reference. Returns false when
     * the reference was already applied, so redelivered webhooks do nothing.
     */
    protected function recordPayment(Site $site, string $reference, callable $apply): bool
    {
        $seen = $site->settings['payment_refs'] ?? [];

        if (in_array($reference, $seen, true)) {
            return false;
        }

        $apply();

        $site->refresh();
        $site->update([
            'settings' => [...$site->settings, 'payment_refs' => array_slice([...$seen, $reference], -self::MAX_REFERENCES)],
        ]);

        return true;
    }

    /**
     * Extends paid time from the later of now and the current paid-through date,
     * so buying early never throws away days already paid for. Publishes a draft,
     * since a paid site is meant to go live.
     */
    protected function addDays(Site $site, int $days): void
    {
        $this->extend($site, fn (Carbon $from) => $from->addDays($days));
    }

    protected function addMonths(Site $site, int $months): void
    {
        $this->extend($site, fn (Carbon $from) => $from->addMonths($months));
    }

    protected function extend(Site $site, callable $move): void
    {
        $from = $site->paid_through !== null && $site->paid_through->isFuture() ? $site->paid_through : now();

        $attributes = ['paid_through' => $move($from->copy())];

        if ($site->isDraft()) {
            $attributes += ['status' => Site::STATUS_PUBLISHED, 'published_at' => $site->published_at ?? now()];
        }

        $site->update($attributes);
    }

    protected function customerFor(Owner $owner): string
    {
        if ($owner->stripe_customer_id === null) {
            $customer = $this->client()->customers->create([
                'email' => $owner->email,
                'metadata' => ['owner_id' => (string) $owner->id],
            ]);

            $owner->update(['stripe_customer_id' => $customer->id]);
        }

        return $owner->stripe_customer_id;
    }

    protected function client(): StripeClient
    {
        return $this->stripe ??= new StripeClient((string) config('services.stripe.secret'));
    }
}
