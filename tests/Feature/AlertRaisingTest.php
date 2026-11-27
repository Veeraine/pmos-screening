<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use App\Screening\AlertRaiser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRaisingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    }

    /**
     * Give the patient a consulted visit on the given date with confirmed symptoms.
     */
    private function visitOn(Patient $patient, string $date, string ...$codes): Visit
    {
        $visit = Visit::factory()->for($patient)->consulted()->create(['visit_date' => $date]);

        $this->confirmSymptoms($visit, $codes);

        return $visit;
    }

    private function raise(Visit $visit): ?Alert
    {
        return app(AlertRaiser::class)->raiseIfNeeded((string) $visit->patient_id, (string) $visit->id);
    }

    public function test_an_alert_is_raised_when_two_features_are_found_at_the_same_visit(): void
    {
        $patient = Patient::factory()->create();
        $visit = $this->visitOn($patient, '2026-10-15', 'absent_periods', 'acne');

        $alert = $this->raise($visit);

        $this->assertTrue($alert->isOpen());
        $this->assertSame((string) $visit->id, $alert->raised_at_emr_visit_id);
        $this->assertNull($alert->previous_alert_id);
        $this->assertSame(['Irregular ovulation', 'Signs of high androgen levels'], array_column($alert->findings, 'label'));
        $this->assertSame([
            'visit_id' => (string) $visit->id,
            'visit_date' => '2026-10-15',
            'symptom' => 'Acne',
            'matched_text' => 'notes mentioning acne',
            'source' => 'detected',
            'is_new' => false,
        ], $alert->findings[1]['evidence'][0]);
    }

    public function test_an_alert_is_raised_when_two_features_come_from_different_visits(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-05-20', 'absent_periods');
        $latest = $this->visitOn($patient, '2026-10-15', 'excess_hair_growth');

        $alert = $this->raise($latest);

        $this->assertSame(
            ['2026-05-20', '2026-10-15'],
            [$alert->findings[0]['evidence'][0]['visit_date'], $alert->findings[1]['evidence'][0]['visit_date']],
        );
    }

    public function test_no_alert_below_the_threshold(): void
    {
        $patient = Patient::factory()->create();
        $visit = $this->visitOn($patient, '2026-10-15', 'acne', 'hair_thinning', 'weight_gain');

        $this->assertNull($this->raise($visit));
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_an_alert_is_not_repeated_while_one_is_open(): void
    {
        $patient = Patient::factory()->create();
        $visit = $this->visitOn($patient, '2026-10-15', 'absent_periods', 'acne');
        $this->raise($visit);

        $this->assertNull($this->raise($visit));
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_no_alert_while_the_patient_is_awaiting_referral_results(): void
    {
        $patient = Patient::factory()->create();
        Alert::factory()->forPatient($patient)->referred('2026-09-01')->create();
        $visit = $this->visitOn($patient, '2026-10-15', 'absent_periods', 'polycystic_ovaries_on_ultrasound');

        $this->assertNull($this->raise($visit));
    }

    public function test_no_more_alerts_after_pmos_is_confirmed_even_with_new_symptoms(): void
    {
        $patient = Patient::factory()->create();
        Alert::factory()->forPatient($patient)->confirmed('2026-03-01', '2026-04-01')->create();
        $visit = $this->visitOn($patient, '2026-10-15', 'absent_periods', 'acne', 'polycystic_ovaries_on_ultrasound');

        $this->assertNull($this->raise($visit));
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_after_ruled_out_old_symptoms_alone_do_not_raise_a_new_alert(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-02-10', 'irregular_periods', 'acne');
        Alert::factory()->forPatient($patient)->ruledOut('2026-02-10', '2026-04-21')->create();
        $unrelatedVisit = $this->visitOn($patient, '2026-10-15');

        $this->assertNull($this->raise($unrelatedVisit));
    }

    public function test_after_ruled_out_a_new_symptom_that_is_not_a_rotterdam_feature_does_not_count(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-02-10', 'irregular_periods', 'acne');
        Alert::factory()->forPatient($patient)->ruledOut('2026-02-10', '2026-04-21')->create();
        $visit = $this->visitOn($patient, '2026-10-15', 'weight_gain', 'difficulty_conceiving');

        $this->assertNull($this->raise($visit));
    }

    public function test_after_ruled_out_a_symptom_on_the_result_date_itself_does_not_count(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-02-10', 'irregular_periods');
        Alert::factory()->forPatient($patient)->ruledOut('2026-02-10', '2026-04-21')->create();
        $visit = $this->visitOn($patient, '2026-04-21', 'acne');

        $this->assertNull($this->raise($visit));
    }

    public function test_after_ruled_out_a_new_rotterdam_symptom_raises_an_alert_that_mentions_the_earlier_result(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-02-10', 'irregular_periods', 'acne');
        $ruledOut = Alert::factory()->forPatient($patient)->ruledOut('2026-02-10', '2026-04-21')->create();
        $visit = $this->visitOn($patient, '2026-09-29', 'polycystic_ovaries_on_ultrasound');

        $alert = $this->raise($visit);

        $this->assertSame($ruledOut->id, $alert->previous_alert_id);
        $this->assertSame(3, $alert->featureCount());
        $this->assertFalse($alert->findings[0]['evidence'][0]['is_new']);
        $this->assertTrue($alert->findings[2]['evidence'][0]['is_new']);
    }

    public function test_after_a_dont_refer_decision_the_same_new_symptom_rule_applies(): void
    {
        $patient = Patient::factory()->create();
        $sameDayVisit = $this->visitOn($patient, '2026-08-01', 'absent_periods', 'acne');
        $declined = Alert::factory()->forPatient($patient)->notReferred('2026-08-01')->create();

        $this->assertNull($this->raise($sameDayVisit));

        $laterVisit = $this->visitOn($patient, '2026-10-15', 'hair_thinning');
        $alert = $this->raise($laterVisit);

        $this->assertSame($declined->id, $alert->previous_alert_id);
    }

    public function test_saving_a_consultation_raises_the_alert_and_says_so(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Tiwonge', 'last_name' => 'Banda']);
        $this->visitOn($patient, '2026-05-20', 'absent_periods');
        $todaysVisit = Visit::factory()->for($patient)->create();

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->put("/visits/{$todaysVisit->id}/consultation", [
                'notes' => 'Growing facial hair.',
                'decisions' => ['excess_hair_growth' => 'confirm'],
            ])
            ->assertSessionHas('status', 'Consultation saved for Tiwonge Banda. Screening check: 2 of 3 Rotterdam signs found. A screening alert has been raised. Please review it below.');

        $this->assertSame((string) $todaysVisit->id, Alert::sole()->raised_at_emr_visit_id);
    }

    public function test_doctors_see_the_alert_explanation_and_decision_form_on_the_record(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-05-20', 'absent_periods');
        $this->raise($this->visitOn($patient, '2026-10-07', 'excess_hair_growth'));

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get("/patients/{$patient->id}")
            ->assertSeeInOrder([
                'Screening alert: refer for PMOS screening?',
                '2 of the 3 Rotterdam signs have been recorded:',
                'Irregular ovulation', 'Absent periods', 'Visit on 20 May 2026', 'notes mentioning absent_periods',
                'Signs of high androgen levels', 'Excess hair growth', 'Visit on 07 Oct 2026',
                'Refer for screening', "Don't refer", 'Save decision',
                'Summary',
            ], false);
    }

    public function test_the_alert_mentions_an_earlier_ruled_out_result(): void
    {
        $patient = Patient::factory()->create();
        $this->visitOn($patient, '2026-02-10', 'irregular_periods', 'acne');
        Alert::factory()->forPatient($patient)->ruledOut('2026-02-10', '2026-04-21')->create();
        $this->raise($this->visitOn($patient, '2026-09-29', 'polycystic_ovaries_on_ultrasound'));

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get("/patients/{$patient->id}")
            ->assertSee('PMOS was ruled out on 21 Apr 2026.')
            ->assertSeeInOrder(['Polycystic ovaries on ultrasound', 'Ultrasound findings (polycystic ovaries)', 'new']);
    }

    public function test_nurses_see_only_that_an_alert_is_open(): void
    {
        $patient = Patient::factory()->create();
        $this->raise($this->visitOn($patient, '2026-10-15', 'absent_periods', 'acne'));

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get("/patients/{$patient->id}")
            ->assertSee('Screening alert open — waiting for the doctor to review it.')
            ->assertDontSee('Screening alert: refer for PMOS screening?')
            ->assertDontSee('Save decision');
    }

    public function test_the_doctors_dashboard_lists_open_alerts_and_referrals_awaiting_results(): void
    {
        Alert::factory()->state(['emr_patient_id' => Patient::factory()->state(['first_name' => 'Tiwonge', 'last_name' => 'Banda'])])->create();
        Alert::factory()->state(['emr_patient_id' => Patient::factory()->state(['first_name' => 'Lusungu', 'last_name' => 'Nyirenda'])])->referred('2026-09-08')->create();
        Alert::factory()->state(['emr_patient_id' => Patient::factory()->state(['first_name' => 'Mphatso', 'last_name' => 'Kamanga'])])->confirmed('2026-01-13', '2026-03-02')->create();

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/dashboard')
            ->assertSeeInOrder(['Open alerts', 'Tiwonge Banda', '2 signs', 'Referred: awaiting results', 'Lusungu Nyirenda', 'referred 08 Sep 2026'])
            ->assertDontSee('Mphatso Kamanga');

        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get('/dashboard')
            ->assertDontSee('Open alerts');
    }
}
