<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(9, 30));
    }

    public function test_todays_visits_show_where_each_patient_is_in_the_visit(): void
    {
        $waitingForVitals = Visit::factory()
            ->for(Patient::factory()->state(['first_name' => 'Chikondi', 'last_name' => 'Banda']))
            ->create();
        $waitingForDoctor = Visit::factory()
            ->for(Patient::factory()->state(['first_name' => 'Tamara', 'last_name' => 'Mwale']))
            ->create();
        VitalSign::factory()->for($waitingForDoctor)->create();
        $seen = Visit::factory()
            ->for(Patient::factory()->state(['first_name' => 'Thoko', 'last_name' => 'Phiri']))
            ->consulted()
            ->create();
        VitalSign::factory()->for($seen)->create();

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Chikondi Banda', 'Waiting for vitals', 'Record vitals'])
            ->assertSeeInOrder(['Tamara Mwale', 'Waiting for doctor'])
            ->assertSeeInOrder(['Thoko Phiri', 'Seen'])
            ->assertSee("/visits/{$waitingForVitals->id}/vital-signs")
            ->assertDontSee("/visits/{$waitingForDoctor->id}/vital-signs");
    }

    public function test_doctors_get_a_consult_link_only_for_patients_not_yet_seen(): void
    {
        $waiting = Visit::factory()->create();
        VitalSign::factory()->for($waiting)->create();
        $seen = Visit::factory()->consulted()->create();

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/dashboard')
            ->assertSee("/visits/{$waiting->id}/consultation")
            ->assertDontSee("/visits/{$seen->id}/consultation");
    }

    public function test_visits_from_earlier_days_are_not_listed(): void
    {
        Visit::factory()
            ->for(Patient::factory()->state(['first_name' => 'Chikondi', 'last_name' => 'Banda']))
            ->daysAgo(1)
            ->create();

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get('/dashboard')
            ->assertSee('No patients have been checked in today.')
            ->assertDontSee('Chikondi Banda');
    }

    public function test_doctors_see_todays_visits_without_the_record_vitals_link(): void
    {
        Visit::factory()
            ->for(Patient::factory()->state(['first_name' => 'Chikondi', 'last_name' => 'Banda']))
            ->create();

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/dashboard')
            ->assertSee('Chikondi Banda')
            ->assertDontSee('Record vitals');
    }

    public function test_admins_do_not_see_any_patients_on_the_dashboard(): void
    {
        Visit::factory()
            ->for(Patient::factory()->state(['first_name' => 'Chikondi', 'last_name' => 'Banda']))
            ->create();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee("Today's visits")
            ->assertDontSee('Chikondi Banda');
    }
}
