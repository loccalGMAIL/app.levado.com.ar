<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(NotificationType::cases());

        return [
            'type' => $type,
            'severity' => $type->severity(),
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'action_url' => null,
            'subject_type' => null,
            'subject_id' => null,
            'dedupe_key' => null,
            'meta' => null,
            'read_at' => null,
            'resolved_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved_at' => now()]);
    }
}
