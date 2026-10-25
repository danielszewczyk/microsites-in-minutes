<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteEntry>
 */
class SiteEntryFactory extends Factory
{
    protected $model = SiteEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'message' => fake()->optional()->sentence(),
            'is_amoe' => false,
            'ip_hash' => hash('sha256', fake()->ipv4()),
        ];
    }
}
