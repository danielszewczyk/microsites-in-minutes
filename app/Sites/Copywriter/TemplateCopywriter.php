<?php

namespace App\Sites\Copywriter;

use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The MVP generator: deterministic, template-driven copy assembled from the
 * owner's inputs. Produces a hero, an offer, three feature cards, an about
 * paragraph, a call-to-action and an entry form — plus, for a sweepstakes, a
 * full official-rules block with a free alternate method of entry.
 *
 * Every string here is plain text; the template escapes all of it. The point is
 * a polished page in seconds with no external dependency and total control over
 * the legal wording of a promotion.
 */
class TemplateCopywriter implements Copywriter
{
    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function generate(array $inputs): array
    {
        $business = $this->clean($inputs['business_name'] ?? 'Your Business');
        $prompt = $this->clean($inputs['prompt'] ?? '');
        $isSweepstakes = ($inputs['type'] ?? Site::TYPE_STANDARD) === Site::TYPE_SWEEPSTAKES;

        $content = $isSweepstakes
            ? $this->sweepstakes($business, $prompt, $inputs)
            : $this->standard($business, $prompt, $inputs);

        $content['meta'] = $this->meta($business, $prompt, $isSweepstakes, $content);

        return $content;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected function standard(string $business, string $prompt, array $inputs): array
    {
        $headline = $this->firstFilled($inputs['offer_headline'] ?? null, $business);
        $subhead = $this->firstSentence($prompt) ?: "Welcome to {$business}.";

        return [
            'hero' => [
                'kicker' => $this->clean($inputs['location'] ?? ''),
                'headline' => $headline,
                'subhead' => $this->trim($subhead, 160),
            ],
            'offer' => [
                'heading' => 'What we offer',
                'body' => $prompt !== '' ? $prompt : "{$business} is here to help. Reach out and we'll take it from there.",
                'bullets' => [],
            ],
            'about' => $prompt !== ''
                ? $prompt
                : "{$business} keeps it simple: do good work, treat people well, and make it easy to get in touch.",
            'features' => [
                ['title' => 'Local & dependable', 'text' => "{$business} shows up, does the work, and stands behind it."],
                ['title' => 'Easy to reach', 'text' => 'Send one message and a real person gets back to you.'],
                ['title' => 'No pressure', 'text' => 'Ask a question, get a straight answer, decide in your own time.'],
            ],
            'cta' => [
                'label' => $this->firstFilled($inputs['cta_label'] ?? null, 'Get in touch'),
                'note' => "We'll follow up soon.",
            ],
            'form' => [
                'heading' => 'Request information',
                'note' => 'Fill this in and we will be in touch.',
                'success' => "Thanks — {$business} will get back to you shortly.",
            ],
            'contact' => $this->contact($inputs),
            'rules' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    protected function sweepstakes(string $business, string $prompt, array $inputs): array
    {
        $prize = $this->firstFilled($inputs['prize'] ?? null, 'a great prize');
        $prizePhrase = rtrim($prize, '. ');
        $prizeShort = $this->trim($prizePhrase, 48);
        $start = $this->date($inputs['start_date'] ?? null) ?? Carbon::today();
        $end = $this->date($inputs['end_date'] ?? null) ?? $start->copy()->addDays(30);
        $sponsor = $this->firstFilled($inputs['sponsor'] ?? null, $business);
        $eligibility = $this->firstFilled(
            $inputs['eligibility'] ?? null,
            'Legal U.S. residents of New York who are 18 or older at the time of entry.'
        );
        $prizeValue = $this->clean($inputs['prize_value'] ?? '');
        $amoe = $this->firstFilled(
            $inputs['amoe'] ?? null,
            'No purchase necessary. To enter without using the form, mail a 3x5 card with your '
            .'full name, address, email and phone to the sponsor at the address below. One entry per card. '
            .'Mail-in entries must be received by the end date.'
        );

        $headline = $this->firstFilled($inputs['offer_headline'] ?? null, "Enter to win {$prizeShort}");

        return [
            'hero' => [
                'kicker' => 'Giveaway',
                'headline' => $headline,
                'subhead' => $this->trim($this->firstSentence($prompt) ?: "A giveaway from {$business}.", 160),
            ],
            'offer' => [
                'heading' => 'The prize',
                'body' => $prompt !== '' ? $prompt : "Enter for your chance to win {$prizePhrase}.",
                'bullets' => array_values(array_filter([
                    'Free to enter — no purchase necessary',
                    'Winner drawn on '.$end->format('F j, Y'),
                    $prizeValue !== '' ? 'Approximate prize value: '.$prizeValue : null,
                ])),
            ],
            'about' => $prompt !== ''
                ? $prompt
                : "{$business} is giving away {$prizePhrase}. Enter below — it takes less than a minute.",
            'features' => [
                ['title' => 'Quick to enter', 'text' => 'One short form and you are in — under a minute.'],
                ['title' => 'What you could win', 'text' => $this->trim($prizePhrase, 200)],
                ['title' => 'No purchase necessary', 'text' => 'Entry is always free. See the official rules for the free mail-in method.'],
            ],
            'cta' => [
                'label' => $this->firstFilled($inputs['cta_label'] ?? null, 'Enter the giveaway'),
                'note' => 'Free to enter. No purchase necessary. Open to '.$this->clip($eligibility, 80).'.',
            ],
            'form' => [
                'heading' => 'Enter the giveaway',
                'note' => 'Enter your details below. One entry per person.',
                'success' => "You're entered. Good luck — the winner is drawn on ".$end->format('F j, Y').'.',
            ],
            'contact' => $this->contact($inputs),
            'rules' => [
                'sponsor' => $sponsor,
                'eligibility' => $eligibility,
                'start_date' => $start->format('F j, Y'),
                'end_date' => $end->format('F j, Y'),
                'prize_description' => $prize,
                'prize_value' => $prizeValue !== '' ? $prizeValue : 'See sponsor for details',
                'winner_selection' => 'One winner will be selected in a random drawing from all eligible entries '
                    .'on or about '.$end->copy()->addDays(3)->format('F j, Y')
                    .'. The winner will be notified by email or phone and must respond within 7 days.',
                'amoe' => $amoe,
                'no_purchase' => 'NO PURCHASE NECESSARY. A PURCHASE WILL NOT IMPROVE YOUR CHANCES OF WINNING.',
                'disclaimer' => 'Void where prohibited. This promotion is in no way sponsored, endorsed or '
                    .'administered by, or associated with, any social-media platform. By entering, you agree to '
                    .'these official rules and the sponsor\'s decisions, which are final.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{email: ?string, phone: ?string, location: ?string}
     */
    protected function contact(array $inputs): array
    {
        return [
            'email' => $this->clean($inputs['contact_email'] ?? '') ?: null,
            'phone' => $this->clean($inputs['contact_phone'] ?? '') ?: null,
            'location' => $this->clean($inputs['location'] ?? '') ?: null,
        ];
    }

    /**
     * SEO fields derived from the content: title, meta description, OG.
     *
     * @param  array<string, mixed>  $content
     * @return array{title: string, description: string}
     */
    protected function meta(string $business, string $prompt, bool $isSweepstakes, array $content): array
    {
        $headline = $content['hero']['headline'] ?? $business;
        $title = $isSweepstakes ? "{$headline} — {$business}" : $business;
        $description = $this->trim(
            $content['hero']['subhead'] ?: ($prompt !== '' ? $prompt : $business),
            155
        );

        return [
            'title' => $this->trim($title, 70),
            'description' => $description,
        ];
    }

    protected function firstSentence(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $parts = preg_split('/(?<=[.!?])\s+/', $text, 2);

        return trim($parts[0] ?? $text);
    }

    protected function firstFilled(?string $value, string $fallback): string
    {
        $value = $this->clean((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    protected function trim(string $value, int $max): string
    {
        return Str::limit($this->clean($value), $max, '…');
    }

    /**
     * Truncate without an ellipsis and strip trailing punctuation, so the
     * caller can append its own period cleanly (no "entry...." artefacts).
     */
    protected function clip(string $value, int $max): string
    {
        return rtrim(Str::limit($this->clean($value), $max, ''), ' .,;:—-');
    }

    protected function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
