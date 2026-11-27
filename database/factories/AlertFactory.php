<?php

namespace Database\Factories;

use App\AlertStatus;
use App\Models\Alert;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use App\ScreeningOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state: an open alert with two features.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emr_patient_id' => Patient::factory(),
            'raised_at_emr_visit_id' => fn (array $attributes) => Visit::factory()->consulted()->create([
                'patient_id' => $attributes['emr_patient_id'],
                'visit_date' => today()->subYear()->subDays(Visit::where('patient_id', $attributes['emr_patient_id'])->count())->toDateString(),
            ])->id,
            'status' => AlertStatus::Open,
            'findings' => [
                [
                    'key' => 'irregular_ovulation',
                    'label' => 'Irregular ovulation',
                    'evidence' => [['visit_id' => null, 'visit_date' => today()->toDateString(), 'symptom' => 'Absent periods', 'matched_text' => 'no period for 4 months', 'source' => 'detected', 'is_new' => false]],
                ],
                [
                    'key' => 'high_androgens',
                    'label' => 'Signs of high androgen levels',
                    'evidence' => [['visit_id' => null, 'visit_date' => today()->toDateString(), 'symptom' => 'Acne', 'matched_text' => 'pimples on her chin', 'source' => 'detected', 'is_new' => false]],
                ],
            ],
        ];
    }

    // An alert for the given patient in the simulated Medix.
    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'emr_patient_id' => (string) $patient->id,
        ]);
    }

    // The doctor referred the patient on the given date; results not back yet.
    public function referred(string|Carbon|null $on = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Closed,
            'referred' => true,
            'decided_by' => User::factory()->role(Role::Doctor),
            'decided_at' => Carbon::parse($on ?? now())->setTime(10, 0),
        ]);
    }

    // The doctor decided not to refer, with a reason.
    public function notReferred(string|Carbon|null $on = null, string $reason = 'Symptoms explained by another condition'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::Closed,
            'referred' => false,
            'not_referred_reason' => $reason,
            'decided_by' => User::factory()->role(Role::Doctor),
            'decided_at' => Carbon::parse($on ?? now())->setTime(10, 0),
        ]);
    }

    // Referred, and PMOS was confirmed on the given date.
    public function confirmed(string $referredOn, string $resultOn): static
    {
        return $this->referred($referredOn)->withOutcome(ScreeningOutcome::Confirmed, $resultOn);
    }

    // Referred, and PMOS was ruled out on the given date.
    public function ruledOut(string $referredOn, string $resultOn): static
    {
        return $this->referred($referredOn)->withOutcome(ScreeningOutcome::RuledOut, $resultOn);
    }

    // Add a referral outcome.
    private function withOutcome(ScreeningOutcome $outcome, string $resultOn): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => $outcome,
            'outcome_date' => $resultOn,
            'outcome_recorded_by' => User::factory()->role(Role::Doctor),
            'outcome_recorded_at' => Carbon::parse($resultOn)->setTime(12, 0),
        ]);
    }
}
