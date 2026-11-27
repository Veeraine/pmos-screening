<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * Define the model's default state: a patient checked in today.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'visit_date' => today()->toDateString(),
            'reason' => fake()->randomElement(['Headache', 'Fever', 'Irregular periods', 'Follow-up', 'Abdominal pain']),
            'checked_in_by' => User::factory()->role(Role::Nurse),
        ];
    }

    // A visit where a doctor has already saved the consultation.
    public function consulted(): static
    {
        return $this->state(fn (array $attributes) => [
            'diagnosis' => fake()->randomElement(['Malaria', 'Upper respiratory tract infection', 'Under investigation']),
            'consulted_by' => User::factory()->role(Role::Doctor),
            'consulted_at' => now(),
        ]);
    }

    // A visit on an earlier day.
    public function daysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'visit_date' => today()->subDays($days)->toDateString(),
        ]);
    }
}
