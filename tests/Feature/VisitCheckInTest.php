<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitCheckInTest extends TestCase
/**
 * Tests for checking a patient in for a visit.
 */
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(9, 30));
    }

    private function nurse(): User
    {
        return User::factory()->role(Role::Nurse)->create();
    }

    public function test_doctors_cannot_check_patients_in(): void
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->actingAs($doctor)
            ->post("/patients/{$patient->id}/visits", ['reason' => 'Headache'])
            ->assertForbidden();

        $this->assertDatabaseCount('visits', 0);
    }

    public function test_nurse_checks_a_patient_in_and_goes_straight_to_the_vital_signs_form(): void
    {
        $nurse = $this->nurse();
        $patient = Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda']);

        $response = $this->actingAs($nurse)->post("/patients/{$patient->id}/visits", ['reason' => 'Irregular periods']);

        $visit = Visit::sole();

        $response->assertRedirect("/visits/{$visit->id}/vital-signs")
            ->assertSessionHas('status', 'Chikondi Banda has been checked in.');

        $this->assertTrue($visit->patient->is($patient));
        $this->assertSame('2026-10-13', $visit->visit_date->toDateString());
        $this->assertSame('Irregular periods', $visit->reason);
        $this->assertTrue($visit->checkedInBy->is($nurse));
    }

    public function test_a_reason_for_the_visit_is_required(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->nurse())
            ->post("/patients/{$patient->id}/visits", ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Please enter the reason for the visit.']);

        $this->assertDatabaseCount('visits', 0);
    }

    public function test_a_patient_cannot_be_checked_in_twice_on_the_same_day(): void
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->create();

        $this->actingAs($this->nurse())
            ->post("/patients/{$patient->id}/visits", ['reason' => 'Fever'])
            ->assertSessionHasErrors(['reason' => 'This patient has already been checked in today.']);

        $this->assertDatabaseCount('visits', 1);
    }

    public function test_a_patient_seen_yesterday_can_be_checked_in_today(): void
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->daysAgo(1)->create();

        $this->actingAs($this->nurse())
            ->post("/patients/{$patient->id}/visits", ['reason' => 'Follow-up'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('visits', 2);
    }

    public function test_only_nurses_see_the_check_in_form_on_the_patient_page(): void
    {
        $patient = Patient::factory()->create();

        $this->actingAs($this->nurse())
            ->get("/patients/{$patient->id}")
            ->assertSee('Check in');

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get("/patients/{$patient->id}")
            ->assertDontSee('Check in');
    }

    public function test_the_patient_page_lists_all_visits_newest_first(): void
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->daysAgo(30)->create(['reason' => 'Malaria']);
        Visit::factory()->for($patient)->create(['reason' => 'Irregular periods']);

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get("/patients/{$patient->id}")
            ->assertSeeInOrder(['13 Oct 2026', 'Irregular periods', '13 Sep 2026', 'Malaria']);
    }
}
