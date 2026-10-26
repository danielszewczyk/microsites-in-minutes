<?php

namespace App\Http\Requests;

use App\Models\Site;
use App\Sites\HeroArt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for publishing a new microsite from the builder. The sweepstakes
 * fields are only required when the type is a sweepstakes.
 */
class SiteBuilderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The builder no longer asks for a page type up front — a page is a simple
     * promo by default, and an optional add-on turns it into an event or a
     * sweepstakes. Derive the type from the chosen add-on, unless a type was
     * passed directly (the MCP tool and API still send one).
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('type')) {
            $this->merge([
                'type' => match ($this->input('addon')) {
                    'event' => Site::TYPE_EVENT,
                    'sweepstakes' => Site::TYPE_SWEEPSTAKES,
                    default => Site::TYPE_STANDARD,
                },
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'min:2', 'max:80'],
            'prompt' => ['required', 'string', 'min:10', 'max:1500'],
            'addon' => ['nullable', Rule::in(['event', 'sweepstakes'])],
            'type' => ['required', Rule::in([Site::TYPE_STANDARD, Site::TYPE_EVENT, Site::TYPE_SWEEPSTAKES])],

            'event_date' => ['nullable', 'date'],
            'event_time' => ['nullable', 'string', 'max:60'],
            'venue' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:200'],

            'offer_headline' => ['nullable', 'string', 'max:120'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],

            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hero_image' => ['nullable', 'string', Rule::in(['auto', 'none', ...HeroArt::ILLUSTRATIONS])],
            'ga4_id' => ['nullable', 'string', 'regex:/^G-[A-Z0-9]{4,20}$/'],
            'remove_branding' => ['boolean'],
            'verify_emails' => ['boolean'],
            'verify_phones' => ['boolean'],
            'require_confirmation' => ['boolean'],
            'collect_photo' => ['boolean'],
            'webhook_url' => ['nullable', 'url', 'max:300'],
            'go_live_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:go_live_at'],
            'custom_domain' => ['nullable', 'string', 'max:120'],

            'prize' => ['nullable', 'required_if:type,'.Site::TYPE_SWEEPSTAKES, 'string', 'max:300'],
            'prize_value' => ['nullable', 'string', 'max:60'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sponsor' => ['nullable', 'string', 'max:160'],
            'eligibility' => ['nullable', 'string', 'max:400'],
            'amoe' => ['nullable', 'string', 'max:800'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_color.regex' => 'The accent colour must be a hex value like #0f766e.',
            'ga4_id.regex' => 'A Google Analytics ID looks like G-XXXXXXXXXX.',
            'prize.required_if' => 'Tell entrants what they can win.',
        ];
    }
}
