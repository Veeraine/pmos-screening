<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VitalSign>
 */
class VitalSignFactory extends Factory
{
    /**
     * Define the model's default state: normal adult readings.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'weight_kg' => fake()->randomFloat(1, 45, 95),
            'height_cm' => fake()->randomFloat(1, 150, 175),
            'systolic_bp' => fake()->numberBetween(100, 135),
            'diastolic_bp' => fake()->numberBetween(65, 85),
            'temperature_c' => fake()->randomFloat(1, 36.1, 37.4),
            'pulse_rate' => fake()->numberBetween(60, 95),
            'recorded_by' => User::factory()->role(Role::Nurse),
        ];
    }
}
