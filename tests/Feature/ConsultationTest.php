<?php

namespace Tests\Feature;

use App\Models\Symptom;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(10, 15));
    }

    private function doctor(): User
    {
        return User::factory()->role(Role::Doctor)->create();
    }

    private function symptomId(string $code): int
    {
        return Symptom::where('code', $code)->value('id');
    }

    public function test_nurses_cannot_record_a_consultation(): void
    {
        $visit = Visit::factory()->create();
        $nurse = User::factory()->role(Role::Nurse)->create();

        $this->actingAs($nurse)->get("/visits/{$visit->id}/consultation")->assertForbidden();
        $this->actingAs($nurse)
            ->put("/visits/{$visit->id}/consultation", ['notes' => 'Acne'])
            ->assertForbidden();

        $this->assertFalse($visit->fresh()->isConsulted());
    }

    public function test_the_form_has_a_free_text_box_instead_of_a_tick_list(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->get("/visits/{$visit->id}/consultation")
            ->assertOk()
            ->assertSee('Symptoms and notes')
            ->assertDontSee('name="symptoms[]"', false)
            ->assertDontSee('Possible PMOS symptoms found in your notes');
    }

    public function test_notes_with_no_pmos_words_save_straight_away(): void
    {
        $doctor = $this->doctor();
        $visit = Visit::factory()->create();

        $this->actingAs($doctor)
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => 'Headache and runny nose. Fever 38.5.',
                'diagnosis' => 'Malaria',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/patients/{$visit->patient_id}");

        $visit->refresh();

        $this->assertTrue($visit->isConsulted());
        $this->assertTrue($visit->consultedBy->is($doctor));
        $this->assertSame('Headache and runny nose. Fever 38.5.', $visit->notes);
        $this->assertSame('Malaria', $visit->diagnosis);
        $this->assertCount(0, $this->confirmedSymptomsAt($visit));
    }

    public function test_notes_with_pmos_words_are_not_saved_until_each_suggestion_is_decided(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->from("/visits/{$visit->id}/consultation")
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => "Hasn't had her period in 3 months, not pregnant. Growing facial hair.",
            ])
            ->assertRedirect("/visits/{$visit->id}/consultation")
            ->assertSessionHasErrors(['decisions' => 'Possible PMOS symptoms were found in your notes. Please confirm or remove each one, then save again.']);

        $this->assertFalse($visit->fresh()->isConsulted());
        $this->assertDatabaseCount('confirmed_symptoms', 0);
    }

    public function test_the_form_shows_each_suggestion_with_the_words_it_came_from(): void
    {
        $visit = Visit::factory()->create();
        $doctor = $this->doctor();

        $this->actingAs($doctor)
            ->from("/visits/{$visit->id}/consultation")
            ->put("/visits/{$visit->id}/consultation", ['notes' => 'Growing facial hair. Pimples on her chin.']);

        $response = $this->actingAs($doctor)
            ->get("/visits/{$visit->id}/consultation")
            ->assertSee('Possible PMOS symptoms found in your notes')
            ->assertSeeInOrder(['Excess hair growth', 'Growing ', 'facial hair', 'Acne', 'Pimples', ' on her chin'], false)
            ->assertSee('name="decisions[excess_hair_growth]"', false)
            ->assertSee('name="decisions[acne]"', false);

        $this->assertDoesNotMatchRegularExpression('/name="decisions\[[a-z_]+\]"[^>]*\schecked/', $response->getContent());
    }

    public function test_one_undecided_suggestion_still_stops_the_save(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => 'Growing facial hair. Pimples on her chin.',
                'decisions' => ['excess_hair_growth' => 'confirm'],
            ])
            ->assertSessionHasErrors('decisions');

        $this->assertFalse($visit->fresh()->isConsulted());
    }

    public function test_only_confirmed_symptoms_are_saved_with_the_words_they_came_from(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => "Hasn't had her period in 3 months, not pregnant. Pimples on her chin.",
                'decisions' => ['absent_periods' => 'confirm', 'acne' => 'remove'],
                'diagnosis' => 'Under investigation',
            ])
            ->assertSessionHasNoErrors();

        $symptoms = $this->confirmedSymptomsAt($visit);

        $this->assertSame(['absent_periods'], $symptoms->pluck('symptom.code')->all());
        $this->assertSame('detected', $symptoms[0]->source);
        $this->assertSame("Hasn't had her period in 3 months", $symptoms[0]->matched_text);
        $this->assertSame((string) $visit->patient_id, $symptoms[0]->emr_patient_id);
    }

    public function test_a_decision_must_be_confirm_or_remove(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => 'Acne',
                'decisions' => ['acne' => 'maybe'],
            ])
            ->assertSessionHasErrors(['decisions.acne' => 'The selected decisions.acne is invalid.']);
    }

    public function test_the_doctor_can_add_a_symptom_the_scan_missed(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => 'Cycles have been all over the place.',
                'added_symptoms' => [(string) $this->symptomId('irregular_periods'), null],
            ])
            ->assertSessionHasNoErrors();

        $symptoms = $this->confirmedSymptomsAt($visit);

        $this->assertSame(['irregular_periods'], $symptoms->pluck('symptom.code')->all());
        $this->assertSame('manual', $symptoms[0]->source);
        $this->assertNull($symptoms[0]->matched_text);
    }

    public function test_a_symptom_added_by_hand_must_be_on_the_symptom_list(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", ['added_symptoms' => ['9999']])
            ->assertSessionHasErrors(['added_symptoms.0' => 'The added symptom is not on the symptom list.']);

        $this->assertFalse($visit->fresh()->isConsulted());
    }

    public function test_words_in_the_menstrual_history_box_are_found_too(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", [
                'notes' => 'Feels tired.',
                'menstrual_history' => 'Periods come every 3 months',
            ])
            ->assertSessionHasErrors('decisions');
    }

    public function test_reopening_a_saved_consultation_remembers_earlier_decisions_and_added_symptoms(): void
    {
        $visit = Visit::factory()->consulted()->create(['notes' => 'Growing facial hair. Pimples on her chin.']);
        $this->confirmSymptoms($visit, [
            'excess_hair_growth' => ['matched_text' => 'Growing facial hair'],
            'weight_gain' => ['source' => 'manual'],
        ]);

        $html = $this->actingAs($this->doctor())->get("/visits/{$visit->id}/consultation")->getContent();

        $this->assertMatchesRegularExpression('/name="decisions\[excess_hair_growth\]" value="confirm"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="decisions\[acne\]" value="remove"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/name="added_symptoms\[\]" value="'.$this->symptomId('weight_gain').'"[^>]*checked/', $html);
    }

    public function test_correcting_the_notes_drops_symptoms_that_are_no_longer_mentioned(): void
    {
        $visit = Visit::factory()->consulted()->create(['notes' => 'Acne']);
        $this->confirmSymptoms($visit, ['acne' => ['matched_text' => 'Acne']]);

        $this->actingAs($this->doctor())
            ->put("/visits/{$visit->id}/consultation", ['notes' => 'Headache only.'])
            ->assertSessionHasNoErrors();

        $this->assertCount(0, $this->confirmedSymptomsAt($visit));
    }

    public function test_a_consultation_for_a_past_visit_cannot_be_changed(): void
    {
        $visit = Visit::factory()->daysAgo(1)->consulted()->create(['diagnosis' => 'Malaria']);
        $doctor = $this->doctor();

        $this->actingAs($doctor)->get("/visits/{$visit->id}/consultation")->assertForbidden();
        $this->actingAs($doctor)
            ->put("/visits/{$visit->id}/consultation", ['diagnosis' => 'Changed later'])
            ->assertForbidden();

        $this->assertSame('Malaria', $visit->fresh()->diagnosis);
    }

    public function test_the_form_shows_the_nurses_vital_signs_read_only(): void
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

        $this->actingAs($this->doctor())
            ->get("/visits/{$visit->id}/consultation")
            ->assertSeeInOrder(['70.0 kg', '25.7', '118/76', '37.1 °C', '88 bpm'])
            ->assertDontSee('name="weight_kg"', false);
    }

    public function test_the_form_warns_when_vital_signs_are_not_recorded_yet(): void
    {
        $visit = Visit::factory()->create();

        $this->actingAs($this->doctor())
            ->get("/visits/{$visit->id}/consultation")
            ->assertSee('The nurse has not recorded vital signs for this visit yet.');
    }

    public function test_confirmed_symptoms_and_diagnosis_appear_in_the_patients_visit_history(): void
    {
        $visit = Visit::factory()->consulted()->create(['diagnosis' => 'Under investigation']);
        $this->confirmSymptoms($visit, [
            'absent_periods' => ['matched_text' => 'no period since June'],
            'excess_hair_growth' => ['source' => 'manual'],
        ]);

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get("/patients/{$visit->patient_id}")
            ->assertSeeInOrder(['Visit history', 'From the notes: &quot;no period since June&quot;', 'Absent periods', 'Added by hand', 'Excess hair growth', 'Under investigation'], false)
            ->assertDontSee('Edit consultation');
    }
}
