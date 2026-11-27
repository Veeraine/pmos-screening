<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertDecisionTest extends TestCase
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

    public function test_nurses_cannot_decide_on_an_alert(): void
    {
        $alert = Alert::factory()->create();

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->post("/alerts/{$alert->id}/decision", ['referred' => '1'])
            ->assertForbidden();

        $this->assertTrue($alert->fresh()->isOpen());
    }

    public function test_doctor_refers_the_patient_and_the_record_shows_awaiting_results(): void
    {
        $doctor = $this->doctor();
        $alert = Alert::factory()->create();

        $this->actingAs($doctor)
            ->post("/alerts/{$alert->id}/decision", ['referred' => '1'])
            ->assertRedirect("/patients/{$alert->emr_patient_id}")
            ->assertSessionHas('status', 'Referral recorded. The patient is now awaiting results.');

        $alert->refresh();

        $this->assertFalse($alert->isOpen());
        $this->assertTrue($alert->isAwaitingResults());
        $this->assertSame((string) $doctor->id, $alert->decided_by);
        $this->assertSame('2026-10-15 10:00:00', $alert->decided_at->toDateTimeString());

        $this->actingAs($doctor)
            ->get("/patients/{$alert->emr_patient_id}")
            ->assertSee('Referred: awaiting results')
            ->assertSee('Referred for PMOS screening on 15 Oct 2026 by Dr Mwale.')
            ->assertSee('Record result');
    }

    public function test_a_reason_is_required_when_not_referring(): void
    {
        $alert = Alert::factory()->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/decision", ['referred' => '0', 'not_referred_reason' => ''])
            ->assertSessionHasErrors(['not_referred_reason' => 'Please give a reason for not referring the patient.']);

        $this->assertTrue($alert->fresh()->isOpen());
    }

    public function test_doctor_decides_not_to_refer_with_a_reason(): void
    {
        $alert = Alert::factory()->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/decision", ['referred' => '0', 'not_referred_reason' => 'Symptoms explained by thyroid disease'])
            ->assertSessionHas('status', 'Decision recorded: not referred.');

        $alert->refresh();

        $this->assertFalse($alert->referred);
        $this->assertSame('Symptoms explained by thyroid disease', $alert->not_referred_reason);
        $this->assertFalse($alert->isOpen());
    }

    public function test_a_decision_must_be_chosen(): void
    {
        $alert = Alert::factory()->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/decision", [])
            ->assertSessionHasErrors(['referred' => 'Please choose whether the patient was referred for screening.']);
    }

    public function test_an_alert_that_was_already_decided_cannot_be_decided_again(): void
    {
        $alert = Alert::factory()->notReferred('2026-10-14', 'Pregnant')->create();

        $this->actingAs($this->doctor())
            ->post("/alerts/{$alert->id}/decision", ['referred' => '1'])
            ->assertSessionHasErrors(['decision' => 'This alert has already been decided.']);

        $this->assertFalse($alert->fresh()->referred);
    }

    public function test_the_alert_history_shows_each_decision(): void
    {
        $alert = Alert::factory()->notReferred('2026-10-14', 'Pregnant')->create();

        $this->actingAs($this->doctor())
            ->get("/patients/{$alert->emr_patient_id}")
            ->assertSeeInOrder(['Alert history', 'Alert raised', 'Irregular ovulation, Signs of high androgen levels', 'Not referred 14 Oct 2026'])
            ->assertSee('&quot;Pregnant&quot;', false);
    }
}
