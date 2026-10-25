<?php

namespace Database\Factories;

use App\Models\Owner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

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
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'plan' => 'free',
            'plan_status' => 'active',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
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
