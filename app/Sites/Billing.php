<?php

namespace App\Sites;

use App\Models\Owner;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Pay-to-publish for a single site. A $1 day pass checks out through Stripe's
 * hosted page (Apple Pay on the phone, saved for one-tap extensions), and the
 * $25 monthly plan runs as a Stripe subscription. Paid time lives on the site;
 * the webhook handlers below are the only place it is extended by a payment.
 */
class Billing
{
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
        if (! config('services.stripe.enabled')) {
            return false;
        }

        if ($owner->stripe_customer_id === null || $owner->stripe_payment_method_id === null) {
            return false;
        }

        try {
            // The idempotency key collapses a double-click (or a retry after a
            // timeout) in the same minute into one charge; a genuine second
            // extension the next minute is a new, intended charge.
            $intent = $this->client()->paymentIntents->create([
                'amount' => config('plans.day_pass.price'),
                'currency' => 'usd',
                'customer' => $owner->stripe_customer_id,
                'payment_method' => $owner->stripe_payment_method_id,
                'off_session' => true,
                'confirm' => true,
                'metadata' => ['site_id' => (string) $site->id, 'kind' => 'day'],
            ], ['idempotency_key' => 'extend:'.$site->id.':'.now()->format('YmdHi')]);
        } catch (ApiErrorException) {
            // Any Stripe error (declined card, connection, rate limit, detached
            // method) means no charge to credit — send the owner to Checkout.
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

        // Already applied (a redelivery): skip before any Stripe call or credit.
        if (DB::table('payments')->where('reference', 'cs:'.$session['id'])->exists()) {
            return;
        }

        $owner = $site->owner;

        if (($session['mode'] ?? null) === 'subscription') {
            $incoming = $session['subscription'] ?? null;
            $existing = $site->settings['stripe_subscription_id'] ?? null;

            // A race (two open Subscribe tabs) can create a second subscription.
            // Keep the first, cancel the duplicate so it stops billing, and do
            // not credit or overwrite from it.
            if (! empty($existing) && ! empty($incoming) && $existing !== $incoming) {
                $this->cancelSubscriptionQuietly($incoming);
                Log::warning('Cancelled a duplicate subscription for a site', [
                    'site_id' => $site->id, 'kept' => $existing, 'cancelled' => $incoming,
                ]);

                return;
            }

            $this->recordPayment($site, 'cs:'.$session['id'], function () use ($site, $incoming): void {
                $this->addMonths($site, 1);
                $site->update(['settings' => [...$site->settings, 'stripe_subscription_id' => $incoming]]);
            });

            return;
        }

        $intentId = $session['payment_intent'] ?? null;

        // Fetch the saved card id BEFORE recordPayment: no Stripe network call
        // belongs inside its DB transaction. If this throws, the webhook 500s
        // and Stripe retries — nothing was credited yet, so the retry credits
        // exactly once.
        $paymentMethod = $intentId !== null
            ? $this->client()->paymentIntents->retrieve($intentId)->payment_method
            : null;

        $this->recordPayment($site, 'cs:'.$session['id'], function () use ($site, $owner, $paymentMethod): void {
            $this->addDays($site, config('plans.day_pass.days'));

            if ($paymentMethod !== null && $owner !== null) {
                $owner->update(['stripe_payment_method_id' => $paymentMethod]);
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
        // Only a regular renewal cycle adds a month. The first invoice is
        // covered by Checkout (subscription_create), and every other reason
        // (updates, manual, thresholds) must not credit time.
        if (($invoice['billing_reason'] ?? null) !== 'subscription_cycle') {
            return;
        }

        // The subscription id moved under parent.subscription_details on newer
        // Stripe API versions; read either, and never match on a null (which
        // would become IS NULL and hit every subscription-less site).
        $subscriptionId = $invoice['subscription']
            ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);

        if (empty($subscriptionId)) {
            return;
        }

        $site = Site::query()->where('settings->stripe_subscription_id', $subscriptionId)->first();

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
        $subscriptionId = $subscription['id'] ?? null;

        if (empty($subscriptionId)) {
            return;
        }

        DB::transaction(function () use ($subscriptionId): void {
            $site = Site::query()->where('settings->stripe_subscription_id', $subscriptionId)->lockForUpdate()->first();

            if ($site !== null) {
                $site->update(['settings' => [...$site->settings, 'stripe_subscription_id' => null]]);
            }
        });
    }

    /**
     * Cancel a subscription, ignoring a Stripe error (already gone / unreachable).
     */
    protected function cancelSubscriptionQuietly(string $subscriptionId): void
    {
        if (! config('services.stripe.enabled')) {
            return;
        }

        try {
            $this->client()->subscriptions->cancel($subscriptionId);
        } catch (ApiErrorException) {
            // The caller logs the duplicate; nothing more to do here.
        }
    }

    /**
     * Runs the paid-time change once per payment reference. Returns false when
     * the reference was already applied, so redelivered webhooks do nothing.
     */
    protected function recordPayment(Site $site, string $reference, callable $apply): bool
    {
        return DB::transaction(function () use ($site, $reference, $apply): bool {
            // Take the site row lock FIRST — before the payments insert, whose
            // FK also locks the site — so concurrent webhooks for this site
            // acquire locks in the same order and cannot deadlock on Postgres.
            Site::query()->whereKey($site->id)->lockForUpdate()->first();

            // INSERT ... ON CONFLICT DO NOTHING: a redelivered or concurrent
            // webhook that re-uses the reference inserts nothing and is skipped.
            // insertOrIgnore (vs catching a unique violation) does not abort the
            // surrounding transaction on Postgres.
            $inserted = DB::table('payments')->insertOrIgnore([
                'site_id' => $site->id,
                'reference' => $reference,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            // Read the current paid-through under the lock before the math runs.
            $site->refresh();

            $apply();

            return true;
        });
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
        // No overflow: a month added to Jan 31 lands on Feb 28/29, not Mar 3.
        $this->extend($site, fn (Carbon $from) => $from->addMonthsNoOverflow($months));
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

    /**
     * A Stripe Billing Portal session so the owner can update their card or
     * cancel the monthly plan themselves. Null when there is no customer yet.
     */
    public function billingPortalUrl(Owner $owner, string $returnUrl): ?string
    {
        if ($owner->stripe_customer_id === null) {
            return null;
        }

        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $owner->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
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
