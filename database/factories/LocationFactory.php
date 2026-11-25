<?php

namespace Database\Factories;

use App\Enums\LocationKind;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'is_default' => false,
            'active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    /** La cámara de leudado del negocio. */
    public function proofingChamber(): static
    {
        return $this->state([
            'name' => 'Cámara de leudado',
            'kind' => LocationKind::ProofingChamber->value,
            'is_default' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
