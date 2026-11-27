<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatientRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 14)->setTime(11, 0));
    }

    private function doctor(): User
    {
        return User::factory()->role(Role::Doctor)->create();
    }

    /**
     * The HTML of the summary box at the top of the patient record.
     */
    private function summaryHtml(Patient $patient): string
    {
        $html = $this->actingAs($this->doctor())->get("/patients/{$patient->id}")->assertOk()->getContent();

        return Str::between($html, 'id="summary-heading"', 'id="history-heading"');
    }

    public function test_the_summary_shows_vital_signs_from_the_most_recent_visit_that_has_them(): void
    {
        $patient = Patient::factory()->create();
        VitalSign::factory()->for(Visit::factory()->for($patient)->daysAgo(90))->create([
            'weight_kg' => 64, 'height_cm' => 160, 'systolic_bp' => 110, 'diastolic_bp' => 70, 'temperature_c' => 36.5,
        ]);
        VitalSign::factory()->for(Visit::factory()->for($patient)->daysAgo(30))->create([
            'weight_kg' => 72, 'height_cm' => 160, 'systolic_bp' => 128, 'diastolic_bp' => 84, 'temperature_c' => 37.2,
        ]);
        Visit::factory()->for($patient)->create();

        $summary = $this->summaryHtml($patient);

        $this->assertStringContainsString('72.0 kg', $summary);
        $this->assertStringContainsString('28.1', $summary);
        $this->assertStringContainsString('128/84', $summary);
        $this->assertStringContainsString('37.2 °C', $summary);
        $this->assertStringContainsString('Measured 14 Sep 2026', $summary);
        $this->assertStringNotContainsString('64.0 kg', $summary);
    }

    public function test_the_summary_shows_the_latest_menstrual_history(): void
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->daysAgo(60)->consulted()->create(['menstrual_history' => 'Regular 28-day cycles']);
        Visit::factory()->for($patient)->daysAgo(10)->consulted()->create(['menstrual_history' => 'No period for 4 months']);
        Visit::factory()->for($patient)->consulted()->create(['menstrual_history' => null]);

        $summary = $this->summaryHtml($patient);

        $this->assertStringContainsString('No period for 4 months', $summary);
        $this->assertStringContainsString('Recorded 04 Oct 2026', $summary);
        $this->assertStringNotContainsString('Regular 28-day cycles', $summary);
    }

    public function test_the_summary_lists_diagnoses_from_visits_and_from_registration(): void
    {
        $patient = Patient::factory()->create(['previous_diagnoses' => 'Asthma']);
        Visit::factory()->for($patient)->daysAgo(200)->consulted()->create(['diagnosis' => 'Malaria']);
        Visit::factory()->for($patient)->daysAgo(30)->consulted()->create(['diagnosis' => 'Urinary tract infection']);
        Visit::factory()->for($patient)->daysAgo(5)->create(['diagnosis' => null]);

        $summary = $this->summaryHtml($patient);

        $this->assertMatchesRegularExpression(
            '/Urinary tract infection.*14 Sep 2026.*Malaria.*28 Mar 2026.*Asthma.*before registration/s',
            $summary,
        );
    }

    public function test_the_summary_explains_when_nothing_has_been_recorded_yet(): void
    {
        $patient = Patient::factory()->create(['previous_diagnoses' => null]);

        $summary = $this->summaryHtml($patient);

        $this->assertStringContainsString('No vital signs recorded yet.', $summary);
        $this->assertStringContainsString('Not recorded yet.', $summary);
        $this->assertStringContainsString('None recorded.', $summary);
    }

    public function test_the_history_shows_every_visit_newest_first_including_unrelated_illnesses(): void
    {
        $patient = Patient::factory()->create();
        $nurse = User::factory()->role(Role::Nurse)->create(['name' => 'Nurse Kumwenda']);
        $doctor = User::factory()->role(Role::Doctor)->create(['name' => 'Dr Mwale']);

        $malariaVisit = Visit::factory()->for($patient)->daysAgo(100)->consulted()->create([
            'reason' => 'Fever',
            'diagnosis' => 'Malaria',
            'checked_in_by' => $nurse->id,
            'consulted_by' => $doctor->id,
        ]);
        VitalSign::factory()->for($malariaVisit)->create(['temperature_c' => 38.9]);

        $pmosVisit = Visit::factory()->for($patient)->daysAgo(7)->consulted()->create([
            'reason' => 'Irregular periods',
            'notes' => 'Oily skin on the face.',
            'diagnosis' => 'Under investigation',
        ]);
        $this->confirmSymptoms($pmosVisit, ['irregular_periods' => ['source' => 'manual'], 'acne' => ['source' => 'manual']]);

        $this->actingAs($this->doctor())
            ->get("/patients/{$patient->id}")
            ->assertSee('2 visits')
            ->assertSeeInOrder([
                'Visit history',
                '07 Oct 2026', 'Irregular periods', 'Irregular periods', 'Acne', 'Oily skin on the face.', 'Under investigation',
                '06 Jul 2026', 'Fever', 'Checked in by Nurse Kumwenda', 'Seen by Dr Mwale', '38.9 °C', 'None', 'Malaria',
            ]);
    }

    public function test_the_record_page_uses_the_same_number_of_queries_however_many_visits_a_patient_has(): void
    {
        $doctor = $this->doctor();

        $queryCountFor = function (int $visitCount) use ($doctor): int {
            $patient = Patient::factory()->create();

            foreach (range(1, $visitCount) as $daysAgo) {
                $visit = Visit::factory()->for($patient)->daysAgo($daysAgo)->consulted()->create();
                VitalSign::factory()->for($visit)->create();
                $this->confirmSymptoms($visit, ['acne']);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($doctor)->get("/patients/{$patient->id}")->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $this->assertSame($queryCountFor(1), $queryCountFor(20));
    }
}
