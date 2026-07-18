<?php

namespace Database\Factories;

use App\Models\MailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailTemplate>
 */
class MailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mail_type' => 'team-invitation',
            'subject' => fake()->sentence(4),
            'intro_text' => fake()->paragraph(),
            'footer_note' => null,
        ];
    }
}
