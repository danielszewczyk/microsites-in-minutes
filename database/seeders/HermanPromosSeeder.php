<?php

namespace Database\Seeders;

use App\Models\Owner;
use App\Sites\SiteComposer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Two Dr. Herman promotion drafts, generated through the real pipeline so they
 * prove the product end to end. They publish only to staging slugs for Daniel's
 * review — never to a real domain.
 *
 * Legal guardrails baked in per CLIENTS.md and DJA counsel:
 *  - prizes are products / swag only, never free or discounted treatment
 *    (healthcare inducement rules);
 *  - prize value kept well under the $5,000 NY/FL registration-and-bond
 *    threshold;
 *  - entry limited to New York residents so other states' rules do not apply;
 *  - the snore promo makes no diagnosis claim and the recording is optional
 *    (sleep data is health data; a dentist treats sleep apnea only after a
 *    physician's diagnosis).
 *
 * Bracketed placeholders ([...]) are for Daniel to confirm before anything goes
 * live: the mailing address, exact dates and the correct phone line.
 */
class HermanPromosSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Owner::query()->firstOrCreate(
            ['email' => 'daniel@thelocalhostcompany.com'],
            [
                'name' => 'Daniel Szewczyk',
                'plan' => 'business',
                'password' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );

        if ($owner->sites()->count() > 0) {
            $this->command?->warn('Herman promos already seeded for this owner; skipping.');

            return;
        }

        $composer = app(SiteComposer::class);

        foreach ([$this->submitYourSmile(), $this->submitYourSnore()] as $input) {
            ['site' => $site, 'token' => $token] = $composer->publish($input, $owner);
            $this->command?->info("Draft: {$site->inputs['business_name']} — {$input['offer_headline']}");
            $this->command?->line('  Live:   '.$site->url());
            $this->command?->line('  Manage: '.route('microsite.manage', $site->slug).'?t='.$token);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function submitYourSmile(): array
    {
        return [
            'owner_email' => 'daniel@thelocalhostcompany.com',
            'owner_name' => 'Daniel Szewczyk',
            'status' => 'draft',
            'business_name' => 'Beyond Dentistry',
            'type' => 'sweepstakes',
            'offer_headline' => 'Submit Your Smile',
            'cta_label' => 'Enter your smile',
            'location' => 'Central Park South, New York',
            'contact_phone' => '(212) 582-3322',
            'accent_color' => '#0e7490',
            'hero_image' => 'smile',
            'prompt' => 'Beyond Dentistry on Central Park South is giving one New York smile a brighter start. '
                .'Enter for your chance to win a professional at-home teeth-whitening kit and a smile-care goodie bag. '
                .'Free to enter, no purchase necessary — just tell us where to reach you.',
            'prize' => 'A professional at-home teeth-whitening kit and a smile-care goodie bag '
                .'(whitening pen, premium toothbrush and dental-care products). Prize is products only.',
            'prize_value' => '$250',
            'sponsor' => 'Beyond Dentistry, [confirm full mailing address], New York, NY',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-28',
            'eligibility' => 'Legal U.S. residents of New York State who are 18 or older at the time of entry. '
                .'Employees of Beyond Dentistry and their immediate families are not eligible. '
                .'The prize is products only and is not dental treatment or a credit toward treatment.',
            'amoe' => 'No purchase necessary. To enter for free without the form, hand-print your full name, '
                .'address, email and phone on a 3x5 card and mail it to Beyond Dentistry, [confirm mailing address], '
                .'New York, NY. One entry per card. Mail-in entries must be received by the end date.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function submitYourSnore(): array
    {
        return [
            'owner_email' => 'daniel@thelocalhostcompany.com',
            'owner_name' => 'Daniel Szewczyk',
            'status' => 'draft',
            'business_name' => 'Beyond Dentistry',
            'type' => 'sweepstakes',
            'offer_headline' => 'Submit Your Snore',
            'cta_label' => 'Enter the giveaway',
            'location' => 'Central Park South, New York',
            'contact_phone' => '(212) 582-3322',
            'accent_color' => '#4338ca',
            'hero_image' => 'leaf',
            'prompt' => 'Rest easier. Beyond Dentistry on Central Park South is giving away a sleep-comfort kit to one '
                .'New York resident. Enter below — free to enter, no purchase necessary. This is a product giveaway '
                .'only; it is not a medical screening, diagnosis or treatment.',
            'prize' => 'A sleep-comfort kit (premium pillow, white-noise machine and sleep-comfort products). '
                .'Prize is products only — not a medical device, screening, diagnosis or treatment.',
            'prize_value' => '$200',
            'sponsor' => 'Beyond Dentistry, [confirm full mailing address], New York, NY',
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-28',
            'eligibility' => 'Legal U.S. residents of New York State who are 18 or older at the time of entry. '
                .'Employees of Beyond Dentistry and their immediate families are not eligible. The prize is products '
                .'only and is not sleep-apnea screening, diagnosis or treatment.',
            'amoe' => 'No purchase necessary. To enter for free without the form, hand-print your full name, address, '
                .'email and phone on a 3x5 card and mail it to Beyond Dentistry, [confirm mailing address], New York, NY. '
                .'One entry per card. Mail-in entries must be received by the end date.',
        ];
    }
}
