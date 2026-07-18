<?php

namespace Database\Factories;

use App\Enums\TenantUserRole;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => TenantUserRole::Admin->value,
            'token' => Str::random(64),
            'accepted_at' => null,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }
}
