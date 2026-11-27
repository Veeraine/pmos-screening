<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Symptom;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use App\Screening\FeatureFinding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScreeningCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    }

    /**
     * Gives the patient a consulted visit with the given confirmed symptoms.
     */
    private function visitWithSymptoms(Patient $patient, int $daysAgo, string ...$codes): Visit
    {
        $visit = Visit::factory()->for($patient)->daysAgo($daysAgo)->consulted()->create();

        $this->confirmSymptoms($visit, $codes);

        return $visit;
    }

    /**
     * The keys of the signs the check found for the patient.
     *
     * @return list<string>
     */
    private function foundFeatureKeys(Patient $patient): array
    {
        return array_map(
            fn (FeatureFinding $feature): string => $feature->key,
            $this->screeningCheck($patient)->foundFeatures(),
        );
    }

    public function test_a_patient_with_no_confirmed_symptoms_has_no_features(): void
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->consulted()->create(['diagnosis' => 'Malaria']);

        $result = $this->screeningCheck($patient);

        $this->assertSame(0, $result->foundCount());
        $this->assertFalse($result->meetsThreshold());
        $this->assertSame(
            ['Irregular ovulation', 'Signs of high androgen levels', 'Polycystic ovaries on ultrasound'],
            array_map(fn (FeatureFinding $feature): string => $feature->label, $result->features),
        );
    }

    public function test_several_symptoms_of_the_same_feature_count_as_one_feature(): void
    {
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 0, 'acne', 'hair_thinning', 'excess_hair_growth');

        $result = $this->screeningCheck($patient);

        $this->assertSame(['high_androgens'], $this->foundFeatureKeys($patient));
        $this->assertFalse($result->meetsThreshold());
    }

    public function test_two_features_at_the_same_visit_reach_the_threshold(): void
    {
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 0, 'absent_periods', 'acne');

        $this->assertSame(['irregular_ovulation', 'high_androgens'], $this->foundFeatureKeys($patient));
        $this->assertTrue($this->screeningCheck($patient)->meetsThreshold());
    }

    public function test_two_features_from_different_visits_reach_the_threshold_and_show_where_they_came_from(): void
    {
        $patient = Patient::factory()->create();
        $olderVisit = $this->visitWithSymptoms($patient, 120, 'polycystic_ovaries_on_ultrasound');
        $newerVisit = $this->visitWithSymptoms($patient, 3, 'irregular_periods');

        $result = $this->screeningCheck($patient);

        $this->assertTrue($result->meetsThreshold());

        [$ovulation, $androgens, $ovaries] = $result->features;

        $this->assertSame((string) $newerVisit->id, $ovulation->evidence[0]['visit']->id);
        $this->assertSame('irregular_periods', $ovulation->evidence[0]['confirmed']->symptom->code);
        $this->assertFalse($androgens->isFound());
        $this->assertSame((string) $olderVisit->id, $ovaries->evidence[0]['visit']->id);
    }

    public function test_evidence_lists_every_matching_symptom_newest_visit_first(): void
    {
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 60, 'acne');
        $this->visitWithSymptoms($patient, 5, 'hair_thinning');

        $androgens = $this->screeningCheck($patient)->features[1];

        $this->assertSame(
            ['hair_thinning', 'acne'],
            array_map(fn (array $item): string => $item['confirmed']->symptom->code, $androgens->evidence),
        );
    }

    public function test_symptoms_outside_the_rotterdam_features_do_not_count(): void
    {
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 0, 'weight_gain', 'dark_skin_patches', 'difficulty_conceiving');

        $this->assertSame([], $this->foundFeatureKeys($patient));
    }

    public function test_the_threshold_comes_from_the_rules_file(): void
    {
        config()->set('screening.alert_threshold', 3);
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 0, 'absent_periods', 'acne');

        $result = $this->screeningCheck($patient);

        $this->assertSame(3, $result->threshold);
        $this->assertFalse($result->meetsThreshold());
    }

    public function test_the_feature_groups_come_from_the_rules_file(): void
    {
        config()->set('screening.rotterdam_features.high_androgens.symptoms', ['excess_hair_growth']);
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 0, 'acne');

        $this->assertSame([], $this->foundFeatureKeys($patient));
    }

    public function test_every_symptom_code_in_the_rules_file_exists_in_the_symptoms_table(): void
    {
        $knownCodes = Symptom::pluck('code')->all();

        foreach (config('screening.rotterdam_features') as $key => $feature) {
            foreach ($feature['symptoms'] as $code) {
                $this->assertContains($code, $knownCodes, "Feature {$key} lists an unknown symptom code: {$code}");
            }
        }

        foreach (array_keys(config('screening.symptom_keywords')) as $code) {
            $this->assertContains($code, $knownCodes, "symptom_keywords lists an unknown symptom code: {$code}");
        }
    }

    public function test_the_check_runs_when_a_consultation_is_saved(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda']);
        $this->visitWithSymptoms($patient, 40, 'acne');
        $todaysVisit = Visit::factory()->for($patient)->create();

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->put("/visits/{$todaysVisit->id}/consultation", [
                'notes' => 'No period for 4 months.',
                'decisions' => ['absent_periods' => 'confirm'],
            ])
            ->assertSessionHas('status', 'Consultation saved for Chikondi Banda. Screening check: 2 of 3 Rotterdam signs found. A screening alert has been raised. Please review it below.');
    }

    public function test_doctors_see_the_screening_check_with_its_evidence_on_the_patient_record(): void
    {
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 40, 'acne');
        $this->visitWithSymptoms($patient, 2, 'absent_periods');

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get("/patients/{$patient->id}")
            ->assertSee('2 of 3 signs found (threshold: 2).')
            ->assertSeeInOrder([
                'Irregular ovulation', 'Found', 'Absent periods', '13 Oct 2026', 'notes mentioning absent_periods',
                'Signs of high androgen levels', 'Found', 'Acne', '05 Sep 2026', 'notes mentioning acne',
                'Polycystic ovaries on ultrasound', 'Not found',
            ]);
    }

    public function test_nurses_do_not_see_the_screening_check(): void
    {
        $patient = Patient::factory()->create();
        $this->visitWithSymptoms($patient, 2, 'absent_periods', 'acne');

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get("/patients/{$patient->id}")
            ->assertOk()
            ->assertDontSee('Screening check (Rotterdam signs)');
    }
}
