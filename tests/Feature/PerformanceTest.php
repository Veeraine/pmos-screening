<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Requirement NFR2: records and alerts load within 3 seconds, so the alert shows while the patient
 * is still in the room. Uses much more data than a normal patient has.
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_MILLISECONDS = 3000;

    private User $nurse;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));

        $this->nurse = User::factory()->role(Role::Nurse)->create();
        $this->doctor = User::factory()->role(Role::Doctor)->create();
    }

    /**
     * Opens a page and returns the response, the time it took and the number of queries.
     *
     * @return array{response: TestResponse, milliseconds: float, queries: int}
     */
    private function timedGet(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $start = hrtime(true);

        $response = $this->actingAs($this->doctor)->get($url);

        $milliseconds = (hrtime(true) - $start) / 1_000_000;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return ['response' => $response, 'milliseconds' => $milliseconds, 'queries' => $queries];
    }

    public function test_a_patient_record_with_200_visits_and_alerts_loads_within_three_seconds(): void
    {
        $patient = Patient::factory()->create(['registered_by' => $this->nurse->id]);

        foreach (range(1, 200) as $daysAgo) {
            $visit = Visit::factory()->for($patient)->daysAgo($daysAgo)->create([
                'checked_in_by' => $this->nurse->id,
                'consulted_by' => $this->doctor->id,
                'consulted_at' => now()->subDays($daysAgo),
                'notes' => 'Irregular periods and pimples on her chin.',
                'diagnosis' => 'Under investigation',
            ]);
            VitalSign::factory()->for($visit)->create(['recorded_by' => $this->nurse->id]);
            $this->confirmSymptoms($visit, [
                'irregular_periods' => ['matched_text' => 'Irregular periods'],
                'acne' => ['matched_text' => 'pimples on her chin'],
            ]);
        }

        Alert::factory()->forPatient($patient)->ruledOut('2026-03-01', '2026-04-01')->create(['decided_by' => $this->doctor->id, 'outcome_recorded_by' => $this->doctor->id]);
        Alert::factory()->forPatient($patient)->create();

        ['response' => $response, 'milliseconds' => $milliseconds, 'queries' => $queries] = $this->timedGet("/patients/{$patient->id}");

        $response->assertOk()
            ->assertSee($patient->visits()->count().' visits')
            ->assertSee('Screening alert: refer for PMOS screening?');
        $this->assertLessThan(self::MAX_MILLISECONDS, $milliseconds, "The patient record took {$milliseconds} ms.");
        $this->assertLessThan(25, $queries, "The patient record ran {$queries} queries.");
    }

    public function test_the_doctors_dashboard_with_100_open_alerts_loads_within_three_seconds(): void
    {
        foreach (range(1, 100) as $number) {
            $patient = Patient::factory()->create(['registered_by' => $this->nurse->id]);
            Alert::factory()->forPatient($patient)->create();

            if ($number <= 80) {
                $visit = Visit::factory()->for($patient)->create(['checked_in_by' => $this->nurse->id]);
                VitalSign::factory()->for($visit)->create(['recorded_by' => $this->nurse->id]);
            }
        }

        ['response' => $response, 'milliseconds' => $milliseconds, 'queries' => $queries] = $this->timedGet('/dashboard');

        $response->assertOk()->assertSee('Open alerts');
        $this->assertLessThan(self::MAX_MILLISECONDS, $milliseconds, "The dashboard took {$milliseconds} ms.");
        $this->assertLessThan(20, $queries, "The dashboard ran {$queries} queries.");
    }

    public function test_searching_among_2000_patients_loads_within_three_seconds(): void
    {
        Patient::factory()->count(2000)->create(['registered_by' => $this->nurse->id]);
        Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda', 'registered_by' => $this->nurse->id]);

        ['response' => $response, 'milliseconds' => $milliseconds] = $this->timedGet('/patients?search=Chikondi+Banda');

        $response->assertOk()->assertSee('Chikondi Banda');
        $this->assertLessThan(self::MAX_MILLISECONDS, $milliseconds, "The search took {$milliseconds} ms.");
    }
}
