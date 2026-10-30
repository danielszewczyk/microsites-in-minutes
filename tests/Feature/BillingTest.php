<?php

namespace Tests\Feature;

use App\Models\Owner;
use App\Models\Site;
use App\Sites\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_day_pass_adds_a_day_and_publishes_a_draft_once(): void
    {
        $site = Site::factory()->create(['status' => Site::STATUS_DRAFT]);
        $session = $this->paidDaySession($site, 'cs_test_1');

        app(Billing::class)->handleCheckoutCompleted($session);
        $site->refresh();

        $this->assertTrue($site->isPaid());
        $this->assertFalse($site->isDraft());
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $site->paid_through->timestamp, 5);

        // A redelivered webhook for the same session must not pay twice.
        $before = $site->paid_through->copy();
        app(Billing::class)->handleCheckoutCompleted($session);

        $this->assertEquals($before->timestamp, $site->refresh()->paid_through->timestamp);
    }

    public function test_buying_early_extends_from_the_paid_through_date(): void
    {
        $site = Site::factory()->create(['paid_through' => now()->addDays(3)]);

        app(Billing::class)->handleCheckoutCompleted($this->paidDaySession($site, 'cs_test_2'));

        $this->assertEqualsWithDelta(now()->addDays(4)->timestamp, $site->refresh()->paid_through->timestamp, 5);
    }

    public function test_the_first_subscription_month_comes_from_checkout_and_renewals_add_a_month(): void
    {
        $site = Site::factory()->create();

        app(Billing::class)->handleCheckoutCompleted([
            'id' => 'cs_test_sub',
            'mode' => 'subscription',
            'payment_status' => 'paid',
            'subscription' => 'sub_123',
            'metadata' => ['site_id' => (string) $site->id, 'kind' => 'monthly'],
        ]);
        $site->refresh();

        $this->assertSame('sub_123', $site->settings['stripe_subscription_id']);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $site->paid_through->timestamp, 5);

        // The invoice that Checkout already covered must not add a second month.
        app(Billing::class)->handleInvoicePaid(['id' => 'in_1', 'billing_reason' => 'subscription_create', 'subscription' => 'sub_123']);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $site->refresh()->paid_through->timestamp, 5);

        app(Billing::class)->handleInvoicePaid(['id' => 'in_2', 'billing_reason' => 'subscription_cycle', 'subscription' => 'sub_123']);
        $this->assertEqualsWithDelta(now()->addMonths(2)->timestamp, $site->refresh()->paid_through->timestamp, 5);
    }

    public function test_a_lapsed_site_is_paused_and_takes_no_entries_when_the_paywall_is_on(): void
    {
        config(['plans.paywall' => true]);
        $site = Site::factory()->withToken('secret-token')->create([
            'status' => Site::STATUS_PUBLISHED,
            'paid_through' => Carbon::now()->subDay(),
        ]);

        $this->assertTrue($site->isLapsed());
        $this->assertFalse($site->acceptsEntries());

        $this->get(route('microsite.show', $site->slug))
            ->assertOk()
            ->assertSee('This page is paused');
    }

    public function test_the_paywall_off_leaves_lapse_and_publishing_alone(): void
    {
        config(['plans.paywall' => false]);
        $site = Site::factory()->withToken('secret-token')->create([
            'status' => Site::STATUS_DRAFT,
            'paid_through' => Carbon::now()->subDay(),
        ]);

        $this->assertFalse($site->isLapsed());

        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.publish', $site->slug))
            ->assertSessionHas('status');

        $this->assertFalse($site->refresh()->isDraft());
    }

    public function test_publishing_with_the_paywall_on_needs_paid_time(): void
    {
        config(['plans.paywall' => true]);
        $site = Site::factory()->withToken('secret-token')->create(['status' => Site::STATUS_DRAFT]);

        $this->withCookie($site->cookieName(), 'secret-token')
            ->post(route('microsite.publish', $site->slug))
            ->assertSessionHas('error', 'Keep it live for $1 a day to publish.');

        $this->assertTrue($site->refresh()->isDraft());
    }

    public function test_an_invoice_with_no_subscription_credits_no_one(): void
    {
        // A null subscription id must not become IS NULL and match every
        // subscription-less site (which would hand out a free month).
        $site = Site::factory()->create(['paid_through' => null]);

        app(Billing::class)->handleInvoicePaid(['id' => 'in_null', 'billing_reason' => 'subscription_cycle', 'subscription' => null]);

        $this->assertNull($site->refresh()->paid_through);
    }

    public function test_only_a_renewal_cycle_invoice_adds_a_month(): void
    {
        $site = Site::factory()->create(['paid_through' => null]);
        $site->update(['settings' => [...$site->settings, 'stripe_subscription_id' => 'sub_x']]);

        foreach (['subscription_update', 'manual', 'subscription_threshold'] as $reason) {
            app(Billing::class)->handleInvoicePaid(['id' => 'in_'.$reason, 'billing_reason' => $reason, 'subscription' => 'sub_x']);
        }

        $this->assertNull($site->refresh()->paid_through);
    }

    public function test_a_new_site_starts_as_a_draft_when_the_paywall_is_on(): void
    {
        config(['plans.paywall' => true]);
        $owner = Owner::factory()->create();

        $this->actingAs($owner)->post('/sites', [
            'business_name' => 'Paywalled Co',
            'prompt' => 'A promo page for a shop that wants more customers.',
            'type' => 'standard',
        ]);

        $this->assertSame(Site::STATUS_DRAFT, Site::query()->firstOrFail()->status);
    }

    public function test_a_second_monthly_checkout_is_blocked(): void
    {
        config(['services.stripe.enabled' => true]);
        $site = Site::factory()->withToken('tok')->create();
        $site->update(['settings' => [...$site->settings, 'stripe_subscription_id' => 'sub_existing']]);

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.billing.checkout', [$site->slug, 'monthly']))
            ->assertSessionHas('error', 'This site already has a monthly subscription.');
    }

    public function test_the_billing_portal_needs_a_stripe_customer(): void
    {
        config(['services.stripe.enabled' => true]);
        $site = Site::factory()->withToken('tok')->create();

        $this->withCookie($site->cookieName(), 'tok')
            ->post(route('microsite.billing.portal', $site->slug))
            ->assertSessionHas('error', 'There is no billing account to manage yet.');
    }

    public function test_extend_with_the_saved_card_is_off_when_stripe_is_disabled(): void
    {
        config(['services.stripe.enabled' => false]);
        $owner = Owner::factory()->create([
            'stripe_customer_id' => 'cus_1',
            'stripe_payment_method_id' => 'pm_1',
        ]);
        $site = Site::factory()->for($owner)->create();

        // The kill switch returns false before any Stripe call, so no charge.
        $this->assertFalse(app(Billing::class)->extendWithSavedCard($site, $owner));
    }

    /**
     * @return array<string, mixed>
     */
    protected function paidDaySession(Site $site, string $id): array
    {
        return [
            'id' => $id,
            'mode' => 'payment',
            'payment_status' => 'paid',
            'payment_intent' => null,
            'metadata' => ['site_id' => (string) $site->id, 'kind' => 'day'],
        ];
    }
}
