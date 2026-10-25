<?php

namespace Database\Factories;

use App\Models\Owner;
use App\Models\Site;
use App\Sites\Copywriter\TemplateCopywriter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = fake()->company();
        $inputs = [
            'business_name' => $business,
            'prompt' => fake()->sentence(12),
            'type' => Site::TYPE_STANDARD,
            'hero_image' => 'star',
            'accent_color' => Site::DEFAULT_ACCENT,
        ];

        return [
            'owner_id' => Owner::factory(),
            'slug' => Site::slugFor($business),
            'manage_token_hash' => hash('sha256', Str::random(40)),
            'type' => Site::TYPE_STANDARD,
            'status' => 'published',
            'inputs' => $inputs,
            'content' => (new TemplateCopywriter)->generate($inputs),
            'settings' => ['accent_color' => Site::DEFAULT_ACCENT, 'hero_image' => 'star', 'ga4_id' => null, 'remove_branding' => false],
            'published_at' => now(),
        ];
    }

    public function sweepstakes(): static
    {
        return $this->state(function (): array {
            $inputs = [
                'business_name' => fake()->company(),
                'prompt' => 'Enter to win a prize.',
                'type' => Site::TYPE_SWEEPSTAKES,
                'prize' => 'a gift basket',
                'prize_value' => '$200',
            ];

            return [
                'type' => Site::TYPE_SWEEPSTAKES,
                'inputs' => $inputs,
                'content' => (new TemplateCopywriter)->generate($inputs),
            ];
        });
    }

    /**
     * Give the site a known manage token (returned so a test can use it).
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (): array => ['manage_token_hash' => hash('sha256', $token)]);
    }
}
