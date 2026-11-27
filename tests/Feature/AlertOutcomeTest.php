<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Role;
use App\ScreeningOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertOutcomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    }

    private function doctor(): User
    {
        return User::factory()->role(Role::Doctor)->create();
    }

    public function test_nurses_cannot_record_an_outcome(): void
    {
        $alert = Alert::factory()->referred('2026-09-08')->create();

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->post("/alerts/{$alert->id}/outcome", ['outcome' => 'confirmed', 'outcome_date' => '2026-10-01'])
            ->assertForbidden();

        $this->assertTrue($alert->fresh()->isAwaitingResults());
    }

    public function test_confirmed_pmos_shows_as_diagnosed_on_the_record(): void
    {
        $doctor = $this->doctor();
        $alert = Alert::factory()->referred('2026-09-08')->create();

        $this->actingAs($doctor)
            ->post("/alerts/{$alert->id}/outcome", ['outcome' => 'confirmed', 'outcome_date' => '2026-10-01'])
            ->assertRedirect("/patients/{$alert->emr_patient_id}")
            ->assertSessionHas('status', 'Result recorded: PMOS confirmed.');

        $alert->refresh();

        $this->assertSame(ScreeningOutcome::Confirmed, $alert->outcome);
        $this->assertSame('2026-10-01', $alert->outcome_date->toDateString());
        $this->assertSame((string) $doctor->id, $alert->outcome_recorded_by);

        foreach ([$doctor, User::factory()->role(Role::Nurse)->create()] as $user) {
            $this->actingAs($user)
                ->get("/patients/{$alert->emr_patient_id}")
                ->assertSee('Diagnosed with PMOS (01 Oct 2026)')
                ->assertDontSee('Referred: awaiting results');
        }
    }

    public function test_ruled_out_pmos_shows_on_the_record(): void
    {
        $alert = Alert::factory()->referred('2026-09-08')->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/outcome", ['outcome' => 'ruled_out', 'outcome_date' => '2026-10-02'])
            ->assertSessionHas('status', 'Result recorded: PMOS ruled out.');

        $this->actingAs($this->doctor())
            ->get("/patients/{$alert->emr_patient_id}")
            ->assertSee('PMOS ruled out (02 Oct 2026)')
            ->assertSee('A new alert will only be raised if new PMOS-related symptoms are confirmed after this date.');
    }

    public function test_the_result_and_date_are_required(): void
    {
        $alert = Alert::factory()->referred('2026-09-08')->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/outcome", [])
            ->assertSessionHasErrors([
                'outcome' => 'Please choose the result: PMOS confirmed or ruled out.',
                'outcome_date' => 'Please enter the date of the result.',
            ]);
    }

    public function test_the_result_date_cannot_be_in_the_future(): void
    {
        $alert = Alert::factory()->referred('2026-09-08')->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/outcome", ['outcome' => 'confirmed', 'outcome_date' => '2026-10-16'])
            ->assertSessionHasErrors(['outcome_date' => 'The date of the result cannot be in the future.']);
    }

    public function test_the_result_date_cannot_be_before_the_referral(): void
    {
        $alert = Alert::factory()->referred('2026-09-08')->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/outcome", ['outcome' => 'ruled_out', 'outcome_date' => '2026-09-07'])
            ->assertSessionHasErrors(['outcome_date' => 'The date of the result cannot be before the patient was referred.']);
    }

    public function test_an_outcome_can_only_be_recorded_for_a_referral_awaiting_results(): void
    {
        $open = Alert::factory()->create();
        $alreadyConfirmed = Alert::factory()->confirmed('2026-01-13', '2026-03-02')->create();
        $doctor = $this->doctor();

        foreach ([$open, $alreadyConfirmed] as $alert) {
            $this->actingAs($doctor)
                ->post("/alerts/{$alert->id}/outcome", ['outcome' => 'ruled_out', 'outcome_date' => '2026-10-15'])
                ->assertSessionHasErrors(['outcome' => 'This referral is not awaiting results.']);
        }

        $this->assertNull($open->fresh()->outcome);
        $this->assertSame(ScreeningOutcome::Confirmed, $alreadyConfirmed->fresh()->outcome);
    }
}
