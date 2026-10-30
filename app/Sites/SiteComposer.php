<?php

namespace App\Sites;

use App\Models\BuildEvent;
use App\Models\Owner;
use App\Models\Site;
use App\Models\SiteAlarm;
use App\Sites\Copywriter\Copywriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds and updates microsites from an owner's inputs: finds or creates the
 * owner, enforces the plan's site cap, generates the content and settings, and
 * mints the manage token that proves control of a site. The caller gets the
 * plain token once, on create; only its hash is stored.
 */
class SiteComposer
{
    public function __construct(protected Copywriter $copywriter) {}

    /**
     * Publish a new microsite.
     *
     * @param  array<string, mixed>  $input  Validated builder input, including owner_email.
     * @return array{site: Site, token: string}
     *
     * @throws \RuntimeException when the owner is over their plan's site cap or the global cap is hit.
     */
    public function publish(array $input, Owner $owner): array
    {
        if (Site::query()->count() >= Site::MAX) {
            throw new \RuntimeException('The site limit has been reached; no more can be published right now.');
        }

        if (! Plans::canPublishAnother($owner)) {
            throw new \RuntimeException(
                'Your '.$owner->plan()['name'].' plan allows '.$owner->plan()['max_sites']
                .' site(s). Upgrade or remove a site to publish another.'
            );
        }

        $inputs = $this->normalise($input);
        $token = Str::random(40);

        $start = microtime(true);

        $site = Site::query()->create([
            'owner_id' => $owner->id,
            'slug' => $this->freeSlug($inputs['business_name']),
            'manage_token_hash' => hash('sha256', $token),
            'type' => $inputs['type'],
            // With the paywall on, a new site starts as a draft so it must be
            // paid for to go live; otherwise it publishes immediately (or as
            // explicitly requested).
            'status' => (config('plans.paywall') || ($input['status'] ?? null) === Site::STATUS_DRAFT)
                ? Site::STATUS_DRAFT
                : Site::STATUS_PUBLISHED,
            'inputs' => $inputs,
            'content' => $this->compose($inputs),
            'settings' => $this->settings($inputs, $owner),
            'published_at' => now(),
        ]);

        $build = $this->meter($owner, $site, BuildEvent::KIND_CREATE, $start);

        // A default alarm, enabled: email the owner at 100 views.
        $site->alarms()->create([
            'metric' => SiteAlarm::VIEWS,
            'threshold' => 100,
            'email' => $owner->email,
            'enabled' => true,
        ]);

        return ['site' => $site, 'token' => $token, 'build' => $build];
    }

    /**
     * Regenerate a site in place from edited inputs: same slug, same link, same
     * manage token. An open page refreshes itself to the new version.
     *
     * @param  array<string, mixed>  $input
     */
    public function regenerate(Site $site, array $input): Site
    {
        $inputs = $this->normalise([...$site->inputs, ...$input]);

        $start = microtime(true);

        $site = DB::transaction(function () use ($site, $inputs): Site {
            $site = Site::query()->lockForUpdate()->findOrFail($site->id);

            $settings = $this->settings($inputs, $site->owner);

            // Preserve the subscription link, which lives in settings but is not
            // a user input, so saving an edit never detaches a subscription.
            if (array_key_exists('stripe_subscription_id', $site->settings)) {
                $settings['stripe_subscription_id'] = $site->settings['stripe_subscription_id'];
            }

            $site->update([
                'type' => $inputs['type'],
                'inputs' => $inputs,
                'content' => $this->compose($inputs, $site->trackIds()),
                'settings' => $settings,
            ]);

            return $site;
        });

        $this->meter($site->owner, $site, BuildEvent::KIND_REGENERATE, $start);

        return $site;
    }

    /**
     * Time a build, store it on the site, and record it against the owner's
     * balance. Returns the build's duration and cost.
     *
     * @return array{ms: int, cents: int}
     */
    protected function meter(Owner $owner, Site $site, string $kind, float $start): array
    {
        $ms = (int) round((microtime(true) - $start) * 1000);
        $event = BuildMeter::record($owner, $site, $kind, $ms);

        $site->forceFill(['last_build_ms' => $ms])->save();

        return ['ms' => $ms, 'cents' => $event->cost_cents];
    }

    /**
     * Generate the page content and stamp each trackable link and button with
     * its stable click-tracking id, carrying forward any id already assigned so
     * an element keeps its identity across regenerates. This is where element
     * identity is minted — at publish time, not at render.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, string>  $existingTrack
     * @return array<string, mixed>
     */
    protected function compose(array $inputs, array $existingTrack = []): array
    {
        $content = $this->copywriter->generate($inputs);
        $content['track'] = ClickTargets::assign($existingTrack);

        return $content;
    }

    /**
     * Normalise and bound the builder input into the stored inputs document.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalise(array $input): array
    {
        $type = in_array($input['type'] ?? '', [Site::TYPE_SWEEPSTAKES, Site::TYPE_EVENT], true)
            ? $input['type']
            : Site::TYPE_STANDARD;

        $inputs = [
            'business_name' => $this->str($input['business_name'] ?? '', 80),
            'prompt' => $this->str($input['prompt'] ?? '', 1500),
            'type' => $type,
            'seo_title' => $this->str($input['seo_title'] ?? '', 70),
            'seo_description' => $this->str($input['seo_description'] ?? '', 160),
            'offer_headline' => $this->str($input['offer_headline'] ?? '', 120),
            'cta_label' => $this->str($input['cta_label'] ?? '', 40),
            'location' => $this->str($input['location'] ?? '', 120),
            'contact_email' => $this->str($input['contact_email'] ?? '', 120),
            'contact_phone' => $this->str($input['contact_phone'] ?? '', 40),
            'accent_color' => $this->hex($input['accent_color'] ?? null),
            'hero_image' => $this->str($input['hero_image'] ?? 'auto', 40),
            'hero_overlay' => (bool) ($input['hero_overlay'] ?? false),
            'ga4_id' => $this->str($input['ga4_id'] ?? '', 40),
            'remove_branding' => (bool) ($input['remove_branding'] ?? false),
            'custom_domain' => $this->str($input['custom_domain'] ?? '', 120),
            'verify_emails' => (bool) ($input['verify_emails'] ?? false),
            'verify_phones' => (bool) ($input['verify_phones'] ?? false),
            'require_confirmation' => (bool) ($input['require_confirmation'] ?? false),
            'require_email_code' => (bool) ($input['require_email_code'] ?? false),
            'collect_photo' => (bool) ($input['collect_photo'] ?? false),
            'collect_dob' => (bool) ($input['collect_dob'] ?? false),
            'webhook_url' => $this->str($input['webhook_url'] ?? '', 300),
            'go_live_at' => $this->str($input['go_live_at'] ?? '', 40),
            'ends_at' => $this->str($input['ends_at'] ?? '', 40),
        ];

        if ($type === Site::TYPE_EVENT) {
            $inputs += [
                'event_date' => $this->str($input['event_date'] ?? '', 40),
                'event_time' => $this->str($input['event_time'] ?? '', 60),
                'venue' => $this->str($input['venue'] ?? '', 160),
                'address' => $this->str($input['address'] ?? '', 200),
            ];
        }

        if ($type === Site::TYPE_SWEEPSTAKES) {
            $inputs += [
                'sponsor' => $this->str($input['sponsor'] ?? '', 160),
                'prize' => $this->str($input['prize'] ?? '', 300),
                'prize_value' => $this->str($input['prize_value'] ?? '', 60),
                'start_date' => $this->str($input['start_date'] ?? '', 40),
                'end_date' => $this->str($input['end_date'] ?? '', 40),
                'eligibility' => $this->str($input['eligibility'] ?? '', 400),
                'amoe' => $this->str($input['amoe'] ?? '', 800),
            ];
        }

        return $inputs;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected function settings(array $inputs, Owner $owner): array
    {
        $hero = $inputs['hero_image'];

        if ($hero === 'auto' || $hero === '') {
            $hero = HeroArt::suggestFor($inputs['business_name'].' '.$inputs['prompt']);
        } elseif ($hero === 'none') {
            $hero = null;
        } elseif (! in_array($hero, HeroArt::ILLUSTRATIONS, true)) {
            $hero = HeroArt::suggestFor($inputs['business_name'].' '.$inputs['prompt']);
        }

        return [
            'accent_color' => $inputs['accent_color'] ?? Site::DEFAULT_ACCENT,
            'hero_image' => $hero,
            'hero_overlay' => (bool) ($inputs['hero_overlay'] ?? false),
            'ga4_id' => $owner->allows('analytics') ? ($inputs['ga4_id'] ?: null) : null,
            'remove_branding' => $inputs['remove_branding'] && $owner->allows('remove_branding'),
            'custom_domain' => $owner->allows('custom_domain') ? ($inputs['custom_domain'] ?: null) : null,
            'verify_emails' => (bool) ($inputs['verify_emails'] ?? false),
            'verify_phones' => (bool) ($inputs['verify_phones'] ?? false),
            'require_confirmation' => (bool) ($inputs['require_confirmation'] ?? false),
            'require_email_code' => (bool) ($inputs['require_email_code'] ?? false),
            'collect_photo' => (bool) ($inputs['collect_photo'] ?? false),
            'collect_dob' => (bool) ($inputs['collect_dob'] ?? false),
            'webhook_url' => $owner->allows('integrations') ? (($inputs['webhook_url'] ?? '') ?: null) : null,
            'go_live_at' => ($inputs['go_live_at'] ?? '') ?: null,
            'ends_at' => ($inputs['ends_at'] ?? '') ?: null,
        ];
    }

    protected function freeSlug(string $businessName): string
    {
        do {
            $slug = Site::slugFor($businessName);
        } while (Site::query()->where('slug', $slug)->exists());

        return $slug;
    }

    protected function str(mixed $value, int $max): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''), $max, '');
    }

    protected function hex(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? $value : Site::DEFAULT_ACCENT;
    }
}
