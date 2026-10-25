<?php

namespace Database\Factories;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Owner>
 */
class OwnerFactory extends Factory
{
    protected $model = Owner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'plan' => 'free',
            'plan_status' => 'active',
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (): array => ['plan' => 'pro']);
    }

    public function business(): static
    {
        return $this->state(fn (): array => ['plan' => 'business']);
    }
}
