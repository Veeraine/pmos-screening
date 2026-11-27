<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitalSignsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(9, 30));
    }

    /**
     * A valid vital signs form.
     *
     * @return array<string, string>
     */
    private function validVitals(array $overrides = []): array
    {
        return [
            'weight_kg' => '70',
            'height_cm' => '165',
            'systolic_bp' => '120',
            'diastolic_bp' => '80',
            'temperature_c' => '36.8',
            'pulse_rate' => '72',
            ...$overrides,
        ];
    }

    private function nurse(): User
    {
        return User::factory()->role(Role::Nurse)->create();
    }

    public function test_doctors_cannot_record_vital_signs(): void
    {
        $visit = Visit::factory()->create();
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->actingAs($doctor)->get("/visits/{$visit->id}/vital-signs")->assertForbidden();
        $this->actingAs($doctor)->put("/visits/{$visit->id}/vital-signs", $this->validVitals())->assertForbidden();

        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_nurse_records_vital_signs_and_bmi_is_calculated(): void
    {
        $nurse = $this->nurse();
        $visit = Visit::factory()->create();

        $this->actingAs($nurse)
            ->put("/visits/{$visit->id}/vital-signs", $this->validVitals())
            ->assertRedirect('/dashboard')
            ->assertSessionHas('status', "Vital signs saved for {$visit->patient->full_name}.");

        $vitals = VitalSign::sole();

        $this->assertTrue($vitals->visit->is($visit));
        $this->assertSame('70.0', $vitals->weight_kg);
        $this->assertSame('165.0', $vitals->height_cm);
        $this->assertSame('25.7', $vitals->bmi);
        $this->assertSame(120, $vitals->systolic_bp);
        $this->assertSame(80, $vitals->diastolic_bp);
        $this->assertSame('36.8', $vitals->temperature_c);
        $this->assertSame(72, $vitals->pulse_rate);
        $this->assertTrue($vitals->recordedBy->is($nurse));
    }

    public function test_correcting_vital_signs_updates_the_same_record_and_recalculates_bmi(): void
    {
        $visit = Visit::factory()->create();
        VitalSign::factory()->for($visit)->create(['weight_kg' => 70, 'height_cm' => 165]);

        $this->actingAs($this->nurse())
            ->put("/visits/{$visit->id}/vital-signs", $this->validVitals(['weight_kg' => '81.7']))
            ->assertSessionHasNoErrors();

        $vitals = VitalSign::sole();

        $this->assertSame('81.7', $vitals->weight_kg);
        $this->assertSame('30.0', $vitals->bmi);
    }

    public function test_vital_signs_other_than_height_are_required_at_every_visit(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->nurse())
            ->put("/visits/{$visit->id}/vital-signs", [])
            ->assertSessionHasErrors([
                'weight_kg' => 'The weight field is required.',
                'systolic_bp' => 'The systolic blood pressure field is required.',
                'diastolic_bp' => 'The diastolic blood pressure field is required.',
                'temperature_c' => 'The temperature field is required.',
                'pulse_rate' => 'The pulse rate field is required.',
            ]);
    }

    public function test_an_impossible_reading_is_rejected(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->nurse())
            ->put("/visits/{$visit->id}/vital-signs", $this->validVitals(['temperature_c' => '3.7']))
            ->assertSessionHasErrors(['temperature_c' => 'The temperature field must be between 30 and 45.']);

        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_systolic_pressure_must_be_higher_than_diastolic(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->nurse())
            ->put("/visits/{$visit->id}/vital-signs", $this->validVitals(['systolic_bp' => '80', 'diastolic_bp' => '120']))
            ->assertSessionHasErrors(['systolic_bp' => 'The systolic (top) number must be higher than the diastolic (bottom) number.']);
    }

    public function test_vital_signs_for_a_past_visit_cannot_be_changed(): void
    {
        $visit = Visit::factory()->daysAgo(1)->create();
        $nurse = $this->nurse();

        $this->actingAs($nurse)->get("/visits/{$visit->id}/vital-signs")->assertForbidden();
        $this->actingAs($nurse)->put("/visits/{$visit->id}/vital-signs", $this->validVitals())->assertForbidden();

        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_height_is_required_at_the_patients_first_visit(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->nurse())
            ->put("/visits/{$visit->id}/vital-signs", $this->validVitals(['height_cm' => '']))
            ->assertSessionHasErrors(['height_cm' => "Height is required at the patient's first visit."]);

        $this->assertDatabaseCount('vital_signs', 0);
    }

    public function test_height_can_be_left_blank_at_later_visits_and_bmi_uses_the_last_recorded_height(): void
    {
        $patient = Patient::factory()->create();
        VitalSign::factory()->for(Visit::factory()->for($patient)->daysAgo(60))->create(['height_cm' => 150]);
        VitalSign::factory()->for(Visit::factory()->for($patient)->daysAgo(20))->create(['height_cm' => 160]);
        $todaysVisit = Visit::factory()->for($patient)->create();

        $this->actingAs($this->nurse())
            ->put("/visits/{$todaysVisit->id}/vital-signs", $this->validVitals(['weight_kg' => '64', 'height_cm' => '']))
            ->assertSessionHasNoErrors();

        $vitals = $todaysVisit->vitalSigns()->sole();

        $this->assertNull($vitals->height_cm);
        $this->assertSame('25.0', $vitals->bmi);
    }

    public function test_a_newly_measured_height_is_used_instead_of_the_last_recorded_one(): void
    {
        $patient = Patient::factory()->create();
        VitalSign::factory()->for(Visit::factory()->for($patient)->daysAgo(20))->create(['height_cm' => 160]);
        $todaysVisit = Visit::factory()->for($patient)->create();

        $this->actingAs($this->nurse())
            ->put("/visits/{$todaysVisit->id}/vital-signs", $this->validVitals(['weight_kg' => '70', 'height_cm' => '165']))
            ->assertSessionHasNoErrors();

        $this->assertSame('25.7', $todaysVisit->vitalSigns()->sole()->bmi);
    }

    public function test_the_form_shows_the_last_recorded_height_and_when_it_was_measured(): void
    {
        $patient = Patient::factory()->create();
        VitalSign::factory()->for(Visit::factory()->for($patient)->daysAgo(20))->create(['height_cm' => 158.5]);
        $todaysVisit = Visit::factory()->for($patient)->create();

        $this->actingAs($this->nurse())
            ->get("/visits/{$todaysVisit->id}/vital-signs")
            ->assertOk()
            ->assertSee('Height (cm, optional)')
            ->assertSee('Last recorded: 158.5 cm on 23 Sep 2026. Leave blank to use it.');
    }

    public function test_doctors_see_vital_signs_on_the_patient_page_without_a_way_to_change_them(): void
    {
        $visit = Visit::factory()->create();
        VitalSign::factory()->for($visit)->create([
            'weight_kg' => 70,
            'height_cm' => 165,
            'systolic_bp' => 118,
            'diastolic_bp' => 76,
            'temperature_c' => 37.1,
            'pulse_rate' => 88,
        ]);

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get("/patients/{$visit->patient_id}")
            ->assertOk()
            ->assertSeeInOrder(['70.0 kg', '25.7', '118/76', '37.1 °C', '88 bpm'])
            ->assertDontSee("/visits/{$visit->id}/vital-signs");
    }
}
