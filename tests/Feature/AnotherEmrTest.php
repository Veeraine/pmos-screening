<?php

namespace Tests\Feature;

use App\Emr\EmrAdapter;
use App\Emr\EmrPatient;
use App\Emr\EmrVisit;
use App\Emr\EmrVitalSigns;
use App\Models\Alert;
use App\Models\ConfirmedSymptom;
use App\Models\Symptom;
use App\Role;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeStaffAccount;
use Tests\Fakes\InMemoryEmrAdapter;
use Tests\TestCase;

/**
 * Swaps in a different EMR (in memory, own style of IDs) to check screening still works
 * with any EMR, while the simulated Medix tables stay empty.
 */
class AnotherEmrTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryEmrAdapter $emr;

    private FakeStaffAccount $doctor;

    private FakeStaffAccount $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));

        $this->emr = new InMemoryEmrAdapter;
        $this->app->instance(EmrAdapter::class, $this->emr);

        $this->doctor = new FakeStaffAccount('MDX-S-7', 'Dr Chisomo Phiri', Role::Doctor);
        $this->nurse = new FakeStaffAccount('MDX-S-3', 'Nurse Alinafe Gondwe', Role::Nurse);
        $this->emr->addStaff($this->doctor);
        $this->emr->addStaff($this->nurse);

        $this->emr->addPatient(new EmrPatient(
            id: 'MDX-P-501',
            patientNumber: 'MX-501',
            firstName: 'Wezi',
            lastName: 'Msiska',
            dateOfBirth: CarbonImmutable::parse('1998-04-02'),
            phone: '+265 991 000 501',
            address: 'Chitawira, Blantyre',
            previousDiagnoses: null,
            registeredAt: CarbonImmutable::parse('2026-05-01 08:00'),
            registeredByName: 'Nurse Alinafe Gondwe',
        ));

        $this->emr->addVisit($this->visit('MDX-V-9001', '2026-06-01', notes: 'No period for 4 months.', consulted: true));
        $this->emr->addVisit($this->visit('MDX-V-9002', '2026-10-15'));

        $earlier = new ConfirmedSymptom;
        $earlier->forceFill([
            'emr_patient_id' => 'MDX-P-501',
            'emr_visit_id' => 'MDX-V-9001',
            'symptom_id' => Symptom::where('code', 'absent_periods')->value('id'),
            'source' => ConfirmedSymptom::SOURCE_DETECTED,
            'matched_text' => 'No period for 4 months',
            'confirmed_by' => 'MDX-S-7',
        ])->save();
    }

    private function visit(string $id, string $date, ?string $notes = null, bool $consulted = false): EmrVisit
    {
        return new EmrVisit(
            id: $id,
            patientId: 'MDX-P-501',
            date: CarbonImmutable::parse($date),
            checkedInAt: CarbonImmutable::parse("{$date} 08:30"),
            reason: 'Follow-up',
            checkedInByName: 'Nurse Alinafe Gondwe',
            vitalSigns: new EmrVitalSigns('64.0', '160.0', '25.0', 118, 76, '36.7', 72),
            notes: $notes,
            menstrualHistory: null,
            diagnosis: null,
            consultedAt: $consulted ? CarbonImmutable::parse("{$date} 09:10") : null,
            consultedByName: $consulted ? 'Dr Chisomo Phiri' : null,
        );
    }

    public function test_saving_a_consultation_raises_an_alert_using_the_emrs_ids(): void
    {
        $this->actingAs($this->doctor)
            ->put('/visits/MDX-V-9002/consultation', [
                'notes' => 'Growing facial hair on her chin.',
                'decisions' => ['excess_hair_growth' => 'confirm'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/patients/MDX-P-501')
            ->assertSessionHas('status', 'Consultation saved for Wezi Msiska. Screening check: 2 of 3 Rotterdam signs found. A screening alert has been raised. Please review it below.');

        $alert = Alert::sole();
        $this->assertSame('MDX-P-501', $alert->emr_patient_id);
        $this->assertSame('MDX-V-9002', $alert->raised_at_emr_visit_id);
        $this->assertSame(['MDX-V-9001', 'MDX-V-9002'], [$alert->findings[0]['evidence'][0]['visit_id'], $alert->findings[1]['evidence'][0]['visit_id']]);

        $this->assertDatabaseHas('confirmed_symptoms', ['emr_patient_id' => 'MDX-P-501', 'emr_visit_id' => 'MDX-V-9002', 'confirmed_by' => 'MDX-S-7']);
        $this->assertSame('Growing facial hair on her chin.', $this->emr->findVisit('MDX-V-9002')->notes);
        $this->assertSame('Dr Chisomo Phiri', $this->emr->findVisit('MDX-V-9002')->consultedByName);
    }

    public function test_nothing_is_stored_in_the_simulated_medix_tables(): void
    {
        $this->actingAs($this->doctor)->put('/visits/MDX-V-9002/consultation', [
            'notes' => 'Growing facial hair on her chin.',
            'decisions' => ['excess_hair_growth' => 'confirm'],
        ]);
        $this->actingAs($this->doctor)->post('/alerts/'.Alert::sole()->id.'/decision', ['referred' => '1']);

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('visits', 0);
        $this->assertDatabaseCount('vital_signs', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_patient_record_shows_the_emrs_details_with_the_screening_data(): void
    {
        $this->actingAs($this->doctor)
            ->get('/patients/MDX-P-501')
            ->assertOk()
            ->assertSee('Wezi Msiska')
            ->assertSee('MX-501')
            ->assertSee('No period for 4 months.')
            ->assertSee('1 of 3 signs found')
            ->assertSee('Patient details, visits and vital signs come from Test EMR.');
    }

    public function test_an_unknown_patient_is_not_found(): void
    {
        $this->actingAs($this->doctor)->get('/patients/MDX-P-999')->assertNotFound();
    }

    public function test_the_dashboard_lists_todays_visits_and_alerts_from_the_emr(): void
    {
        Alert::factory()->state(['emr_patient_id' => 'MDX-P-501', 'raised_at_emr_visit_id' => 'MDX-V-9001'])->create();

        $this->actingAs($this->doctor)
            ->get('/dashboard')
            ->assertSeeInOrder(['Welcome, Dr Chisomo Phiri', 'Open alerts', 'Wezi Msiska', "Today's visits", 'Wezi Msiska', 'MX-501']);
    }

    public function test_the_decision_records_the_doctors_emr_staff_id_and_shows_their_name(): void
    {
        $alert = Alert::factory()->state(['emr_patient_id' => 'MDX-P-501', 'raised_at_emr_visit_id' => 'MDX-V-9001'])->create();

        $this->actingAs($this->doctor)
            ->post("/alerts/{$alert->id}/decision", ['referred' => '1'])
            ->assertRedirect('/patients/MDX-P-501');

        $this->assertSame('MDX-S-7', $alert->fresh()->decided_by);

        $this->actingAs($this->nurse)
            ->get('/patients/MDX-P-501')
            ->assertSee('Referred for PMOS screening on 15 Oct 2026 by Dr Chisomo Phiri.');
    }

    public function test_the_statistics_report_gets_ages_and_bmi_from_the_emr(): void
    {
        Alert::factory()
            ->state(['emr_patient_id' => 'MDX-P-501', 'raised_at_emr_visit_id' => 'MDX-V-9001', 'created_at' => '2026-06-01 09:30:00'])
            ->confirmed('2026-06-01', '2026-09-01')
            ->state(['decided_by' => 'MDX-S-7', 'outcome_recorded_by' => 'MDX-S-7'])
            ->create();

        $report = $this->actingAs($this->doctor)->get('/reports')->assertOk()->viewData('report');

        // Born 2 April 1998, so 28 when PMOS was confirmed; her latest BMI in the EMR is 25.0.
        $this->assertSame(1, collect($report->ageGroups)->firstWhere('label', '25-29')['count']);
        $this->assertSame(1, collect($report->bmiGroups)->firstWhere('label', 'Overweight')['count']);
        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('vital_signs', 0);
    }
}
