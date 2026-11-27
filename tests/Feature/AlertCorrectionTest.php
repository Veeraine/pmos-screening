<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertCorrection;
use App\Models\Patient;
use App\Models\User;
use App\Role;
use App\ScreeningOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    }

    private function doctor(): User
    {
        return User::factory()->role(Role::Doctor)->create(['name' => 'Dr Mwale']);
    }

    public function test_nurses_cannot_correct_a_decision(): void
    {
        $alert = Alert::factory()->notReferred('2026-10-14', 'Pregnant')->create();

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->put("/alerts/{$alert->id}/decision", ['referred' => '1', 'correction_reason' => 'Mistake'])
            ->assertForbidden();

        $this->assertFalse($alert->fresh()->referred);
    }

    public function test_a_reason_is_required_to_correct_a_decision(): void
    {
        $alert = Alert::factory()->notReferred('2026-10-14', 'Pregnant')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/decision", ['referred' => '1', 'correction_reason' => ''])
            ->assertSessionHasErrors(['correction_reason' => 'Please give a reason for the correction.']);

        $this->assertFalse($alert->fresh()->referred);
        $this->assertDatabaseCount('alert_corrections', 0);
    }

    public function test_correcting_a_decision_keeps_the_original_in_the_history(): void
    {
        $doctor = $this->doctor();
        $alert = Alert::factory()->notReferred('2026-10-14', 'Pregnant')->create();

        $this->actingAs($doctor)
            ->put("/alerts/{$alert->id}/decision", ['referred' => '1', 'correction_reason' => 'Decision entered on the wrong patient'])
            ->assertRedirect("/patients/{$alert->emr_patient_id}")
            ->assertSessionHas('status', 'Decision corrected. The original is kept in the alert history.');

        $alert->refresh();
        $correction = AlertCorrection::sole();

        $this->assertTrue($alert->isAwaitingResults());
        $this->assertNull($alert->not_referred_reason);
        $this->assertSame('2026-10-14', $alert->decided_at->toDateString());
        $this->assertSame(AlertCorrection::FIELD_DECISION, $correction->field);
        $this->assertSame(['referred' => false, 'not_referred_reason' => 'Pregnant'], $correction->previous_values);
        $this->assertSame(['referred' => true, 'not_referred_reason' => null], $correction->new_values);
        $this->assertSame((string) $doctor->id, $correction->corrected_by);

        $this->actingAs($doctor)
            ->get("/patients/{$alert->emr_patient_id}")
            ->assertSee('Referred: awaiting results')
            ->assertSee('Corrected 15 Oct 2026 by Dr Mwale: Not referred (&quot;Pregnant&quot;) → Referred. Reason: Decision entered on the wrong patient', false);
    }

    public function test_changing_to_not_referred_still_needs_a_reason_for_not_referring(): void
    {
        $alert = Alert::factory()->referred('2026-10-14')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/decision", ['referred' => '0', 'correction_reason' => 'Patient declined referral'])
            ->assertSessionHasErrors(['not_referred_reason' => 'Please give a reason for not referring the patient.']);
    }

    public function test_a_decision_cannot_be_corrected_once_a_result_is_recorded(): void
    {
        $alert = Alert::factory()->ruledOut('2026-09-01', '2026-10-01')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/decision", ['referred' => '0', 'not_referred_reason' => 'x', 'correction_reason' => 'Mistake'])
            ->assertSessionHasErrors(['correction' => 'This decision can no longer be corrected.']);

        $this->assertTrue($alert->fresh()->referred);
    }

    public function test_an_open_alert_cannot_be_corrected(): void
    {
        $alert = Alert::factory()->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/decision", ['referred' => '1', 'correction_reason' => 'Mistake'])
            ->assertSessionHasErrors(['correction' => 'This decision can no longer be corrected.']);

        $this->assertTrue($alert->fresh()->isOpen());
    }

    public function test_a_correction_that_changes_nothing_is_refused(): void
    {
        $alert = Alert::factory()->referred('2026-10-14')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/decision", ['referred' => '1', 'correction_reason' => 'Just checking'])
            ->assertSessionHasErrors(['correction' => 'The corrected decision is the same as the current one.']);

        $this->assertDatabaseCount('alert_corrections', 0);
    }

    public function test_correcting_a_result_from_ruled_out_to_confirmed_shows_the_diagnosis_and_keeps_the_original(): void
    {
        $alert = Alert::factory()->ruledOut('2026-09-01', '2026-10-01')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/outcome", [
                'outcome' => 'confirmed',
                'outcome_date' => '2026-10-03',
                'correction_reason' => 'Lab report was misread',
            ])
            ->assertSessionHas('status', 'Result corrected. The original is kept in the alert history.');

        $alert->refresh();
        $correction = AlertCorrection::sole();

        $this->assertSame(ScreeningOutcome::Confirmed, $alert->outcome);
        $this->assertSame('2026-10-03', $alert->outcome_date->toDateString());
        $this->assertSame(['outcome' => 'ruled_out', 'outcome_date' => '2026-10-01'], $correction->previous_values);
        $this->assertSame(['outcome' => 'confirmed', 'outcome_date' => '2026-10-03'], $correction->new_values);

        $this->actingAs($this->doctor())
            ->get("/patients/{$alert->emr_patient_id}")
            ->assertSee('Diagnosed with PMOS (03 Oct 2026)')
            ->assertSee('PMOS ruled out (01 Oct 2026) → PMOS confirmed (03 Oct 2026). Reason: Lab report was misread');
    }

    public function test_a_corrected_result_date_follows_the_same_date_rules(): void
    {
        $alert = Alert::factory()->ruledOut('2026-09-01', '2026-10-01')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/outcome", ['outcome' => 'ruled_out', 'outcome_date' => '2026-10-20', 'correction_reason' => 'Typo'])
            ->assertSessionHasErrors(['outcome_date' => 'The date of the result cannot be in the future.']);
    }

    public function test_a_result_cannot_be_corrected_before_one_is_recorded(): void
    {
        $alert = Alert::factory()->referred('2026-09-01')->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$alert->id}/outcome", ['outcome' => 'confirmed', 'outcome_date' => '2026-10-01', 'correction_reason' => 'Mistake'])
            ->assertSessionHasErrors(['correction' => 'This result can no longer be corrected.']);

        $this->assertNull($alert->fresh()->outcome);
    }

    public function test_only_the_patients_most_recent_alert_can_be_corrected(): void
    {
        $older = Alert::factory()->ruledOut('2026-02-10', '2026-04-21')->create();
        Alert::factory()->forPatient(Patient::find($older->emr_patient_id))->create();

        $this->actingAs($this->doctor())
            ->put("/alerts/{$older->id}/outcome", ['outcome' => 'confirmed', 'outcome_date' => '2026-04-21', 'correction_reason' => 'Mistake'])
            ->assertSessionHasErrors(['correction' => 'This result can no longer be corrected.']);

        $this->assertSame(ScreeningOutcome::RuledOut, $older->fresh()->outcome);
    }

    public function test_the_correction_links_appear_only_on_the_most_recent_decided_alert(): void
    {
        $older = Alert::factory()->ruledOut('2026-02-10', '2026-04-21')->create();
        Alert::factory()->forPatient(Patient::find($older->emr_patient_id))->notReferred('2026-10-01', 'Pregnant')->create();

        $html = $this->actingAs($this->doctor())->get("/patients/{$older->emr_patient_id}")->getContent();

        $this->assertSame(1, substr_count($html, '>Correct decision</summary>'));
        $this->assertSame(0, substr_count($html, '>Correct result</summary>'));
    }
}
