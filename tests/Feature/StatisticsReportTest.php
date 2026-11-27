<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Reports\ScreeningReport;
use App\Role;
use Database\Factories\AlertFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatisticsReportTest extends TestCase
{
    use RefreshDatabase;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        // Past 12 months is then 1 Dec 2025 to 24 Nov 2026.
        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));

        $this->doctor = User::factory()->role(Role::Doctor)->create();
    }

    private function report(string $query = ''): ScreeningReport
    {
        return $this->actingAs($this->doctor)->get('/reports'.$query)->assertOk()->viewData('report');
    }

    /**
     * An alert raised on the given date.
     */
    private function alert(string $raisedOn, ?Patient $patient = null): AlertFactory
    {
        $factory = Alert::factory()->state(['created_at' => "{$raisedOn} 09:30:00"]);

        return $patient ? $factory->forPatient($patient) : $factory;
    }

    /**
     * Rotterdam findings with the given sign keys, as stored on an alert
     *
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function findings(array $keys): array
    {
        return array_map(fn (string $key): array => [
            'key' => $key,
            'label' => config("screening.rotterdam_features.{$key}.label"),
            'evidence' => [],
        ], $keys);
    }

    /**
     * Gives a patient vital signs at a visit on the given date.
     */
    private function vitalSigns(Patient $patient, string $date, float $weightKg, float $heightCm = 160.0): void
    {
        $visit = Visit::factory()->consulted()->create(['patient_id' => $patient->id, 'visit_date' => $date]);
        VitalSign::factory()->create(['visit_id' => $visit->id, 'weight_kg' => $weightKg, 'height_cm' => $heightCm]);
    }


    public function test_only_doctors_can_see_the_report(): void
    {
        $this->get('/reports')->assertRedirect('/login');

        foreach ([Role::Nurse, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/reports')->assertForbidden();
        }

        $this->actingAs($this->doctor)->get('/reports')->assertOk()->assertSee('PMOS screening statistics');
    }

    public function test_the_reports_link_is_only_shown_to_doctors(): void
    {
        $this->actingAs($this->doctor)->get('/dashboard')->assertSee('href="'.route('reports.index').'"', false);

        foreach ([Role::Nurse, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/dashboard')
                ->assertDontSee('href="'.route('reports.index').'"', false);
        }
    }

    public function test_the_page_has_a_print_button(): void
    {
        $this->actingAs($this->doctor)->get('/reports')->assertSee('window.print()', false)->assertSee('Print');
    }


    public function test_the_default_period_is_the_past_12_months(): void
    {
        $report = $this->report();

        $this->assertSame('2025-12-01', $report->period->from->toDateString());
        $this->assertSame('2026-11-24', $report->period->to->toDateString());
        $this->assertCount(12, $report->diagnosesByMonth);
        $this->assertSame('Dec 2025', $report->diagnosesByMonth[0]['label']);
        $this->assertSame('Nov 2026', $report->diagnosesByMonth[11]['label']);
    }

    public function test_the_other_periods(): void
    {
        $sixMonths = $this->report('?period=6m');
        $this->assertSame(['2026-06-01', '2026-11-24'], [$sixMonths->period->from->toDateString(), $sixMonths->period->to->toDateString()]);

        $thisYear = $this->report('?period=year');
        $this->assertSame(['2026-01-01', '2026-11-24'], [$thisYear->period->from->toDateString(), $thisYear->period->to->toDateString()]);

        $custom = $this->report('?period=custom&from=2026-03-10&to=2026-04-20');
        $this->assertSame(['2026-03-10', '2026-04-20'], [$custom->period->from->toDateString(), $custom->period->to->toDateString()]);
        $this->assertSame(['Mar 2026', 'Apr 2026'], array_column($custom->diagnosesByMonth, 'label'));
    }

    public function test_custom_dates_must_be_valid(): void
    {
        $this->actingAs($this->doctor)
            ->get('/reports?period=custom&from=2026-05-01&to=2026-04-01')
            ->assertRedirect(route('reports.index'))
            ->assertSessionHasErrors(['to' => 'The last day cannot be before the first day.']);

        $this->actingAs($this->doctor)
            ->get('/reports?period=custom&from=2026-05-01')
            ->assertSessionHasErrors(['to' => 'Please enter the last day of the period.']);

        $this->actingAs($this->doctor)
            ->get('/reports?period=custom&from=2026-05-01&to=2026-12-31')
            ->assertSessionHasErrors(['to' => 'The last day cannot be in the future.']);

        $this->actingAs($this->doctor)
            ->get('/reports?period=forever')
            ->assertSessionHasErrors('period');
    }

    // 1. Summary and 4. referral results

    public function test_the_summary_counts_each_event_by_its_own_date(): void
    {
        $this->alert('2026-03-01')->create();                                       // open
        $this->alert('2026-10-01')->referred('2026-10-01')->create();               // awaiting results
        $this->alert('2026-05-10')->notReferred('2026-05-10')->create();
        $this->alert('2026-02-01')->confirmed('2026-02-01', '2026-03-15')->create();
        $this->alert('2026-04-01')->ruledOut('2026-04-01', '2026-05-01')->create();

        // Raised and referred before the period, confirmed in it: counts only as a diagnosis.
        $this->alert('2025-11-20')->confirmed('2025-11-20', '2026-01-10')->create();

        // Everything before the period.
        $this->alert('2025-09-01')->ruledOut('2025-09-01', '2025-11-30')->create();
        $this->alert('2025-10-01')->referred('2025-10-01')->create();

        // Edges of the period: the first day counts, so does the last.
        $this->alert('2026-11-24')->notReferred('2026-11-24')->create();
        $this->alert('2025-12-01')->ruledOut('2025-12-01', '2025-12-01')->create();

        $report = $this->report();

        $this->assertSame([
            'raised' => 7,
            'referred' => 4,
            'not_referred' => 2,
            'confirmed' => 2,
            'ruled_out' => 2,
            'awaiting' => 1,
        ], $report->summary);
        $this->assertSame(50, $report->confirmedPercent);
    }

    public function test_the_summary_for_a_custom_period(): void
    {
        $this->alert('2026-02-01')->confirmed('2026-02-01', '2026-03-15')->create();
        $this->alert('2026-03-02')->confirmed('2026-03-02', '2026-03-31')->create();
        $this->alert('2026-03-05')->ruledOut('2026-03-05', '2026-04-01')->create();

        $report = $this->report('?period=custom&from=2026-03-01&to=2026-03-31');

        $this->assertSame(['raised' => 2, 'referred' => 2, 'not_referred' => 0, 'confirmed' => 2, 'ruled_out' => 0, 'awaiting' => 0], $report->summary);
        $this->assertSame(100, $report->confirmedPercent);
    }

    public function test_the_confirmed_percentage_is_left_blank_without_results(): void
    {
        $this->alert('2026-10-01')->referred('2026-10-01')->create();

        $this->assertNull($this->report()->confirmedPercent);
        $this->actingAs($this->doctor)->get('/reports')->assertSee('No referral results were recorded in this period.');
    }

    // 2. Age groups

    public function test_patients_are_grouped_by_their_age_on_the_day_pmos_was_confirmed(): void
    {
        $cases = [
            ['2006-03-15', '2026-03-14'],   // 19, the day before her 20th birthday: under 20
            ['2006-03-15', '2026-03-15'],   // 20 on her birthday: 20-24
            ['2001-06-01', '2026-05-31'],   // 24 when confirmed, 25 today: still 20-24
            ['1996-01-10', '2026-02-01'],   // 30: 30-34
            ['1986-12-01', '2026-11-20'],   // 39: 35-39
            ['1986-01-01', '2026-02-01'],   // 40: 40 and over
            ['1980-07-07', '2026-07-07'],   // 46: 40 and over
        ];

        foreach ($cases as [$dateOfBirth, $confirmedOn]) {
            $patient = Patient::factory()->create(['date_of_birth' => $dateOfBirth]);
            $this->alert('2025-12-15', $patient)->confirmed('2025-12-15', $confirmedOn)->create();
        }

        $report = $this->report();

        $this->assertSame([
            ['label' => 'Under 20', 'count' => 1],
            ['label' => '20-24', 'count' => 2],
            ['label' => '25-29', 'count' => 0],
            ['label' => '30-34', 'count' => 1],
            ['label' => '35-39', 'count' => 1],
            ['label' => '40 and over', 'count' => 2],
        ], $report->ageGroups);
        $this->assertSame(7, $report->diagnosedPatients);
    }

    public function test_ruled_out_and_waiting_patients_are_not_in_the_age_groups(): void
    {
        $this->alert('2026-03-01')->ruledOut('2026-03-01', '2026-04-01')->create();
        $this->alert('2026-03-01')->referred('2026-03-01')->create();

        $this->assertSame(0, array_sum(array_column($this->report()->ageGroups, 'count')));
    }

    public function test_the_age_groups_come_from_the_config_file(): void
    {
        config(['screening.report.age_groups' => [
            ['label' => 'Under 30', 'to' => 29],
            ['label' => '30 and over', 'from' => 30],
        ]]);

        $patient = Patient::factory()->create(['date_of_birth' => '1990-01-01']);
        $this->alert('2026-03-01', $patient)->confirmed('2026-03-01', '2026-04-01')->create();

        $this->assertSame([
            ['label' => 'Under 30', 'count' => 0],
            ['label' => '30 and over', 'count' => 1],
        ], $this->report()->ageGroups);
    }

    public function test_the_age_table_and_chart_are_shown(): void
    {
        $patient = Patient::factory()->create(['date_of_birth' => '1998-04-02']);
        $this->alert('2026-03-01', $patient)->confirmed('2026-03-01', '2026-04-01')->create();

        $this->actingAs($this->doctor)
            ->get('/reports')
            ->assertSeeInOrder(['Patients diagnosed with PMOS by age', 'Under 20', '20-24', '25-29', '30-34', '35-39', '40 and over'])
            ->assertSee('Bar chart of patients diagnosed with PMOS by age group');
    }

    // 3. Diagnoses per month

    public function test_diagnoses_are_counted_in_the_month_of_the_result(): void
    {
        $this->alert('2025-12-01')->confirmed('2025-12-01', '2026-01-05')->create();
        $this->alert('2025-12-10')->confirmed('2025-12-10', '2026-01-28')->create();
        $this->alert('2026-09-01')->confirmed('2026-09-01', '2026-11-02')->create();
        $this->alert('2026-09-01')->ruledOut('2026-09-01', '2026-10-02')->create();

        $counts = array_column($this->report()->diagnosesByMonth, 'count', 'label');

        $this->assertSame(2, $counts['Jan 2026']);
        $this->assertSame(0, $counts['Oct 2026']);
        $this->assertSame(1, $counts['Nov 2026']);
        $this->assertSame(3, array_sum($counts));
    }

    // 5. Rotterdam signs

    public function test_signs_and_combinations_among_diagnosed_patients(): void
    {
        $combinations = [
            ['irregular_ovulation', 'high_androgens'],
            ['irregular_ovulation', 'high_androgens'],
            ['irregular_ovulation', 'polycystic_ovaries'],
            ['irregular_ovulation', 'high_androgens', 'polycystic_ovaries'],
        ];

        foreach ($combinations as $keys) {
            $this->alert('2026-03-01')->confirmed('2026-03-01', '2026-04-01')->create(['findings' => $this->findings($keys)]);
        }


        $this->alert('2026-03-01')->ruledOut('2026-03-01', '2026-04-01')->create(['findings' => $this->findings(['high_androgens', 'polycystic_ovaries'])]);

        $report = $this->report();

        $this->assertSame([
            ['label' => 'Irregular ovulation', 'count' => 4, 'percent' => 100],
            ['label' => 'Signs of high androgen levels', 'count' => 3, 'percent' => 75],
            ['label' => 'Polycystic ovaries on ultrasound', 'count' => 2, 'percent' => 50],
        ], $report->signs);

        $this->assertSame([
            ['label' => 'Irregular ovulation + Signs of high androgen levels', 'count' => 2, 'percent' => 50],
            ['label' => 'Irregular ovulation + Polycystic ovaries on ultrasound', 'count' => 1, 'percent' => 25],
            ['label' => 'Signs of high androgen levels + Polycystic ovaries on ultrasound', 'count' => 0, 'percent' => 0],
            ['label' => 'Irregular ovulation + Signs of high androgen levels + Polycystic ovaries on ultrasound', 'count' => 1, 'percent' => 25],
        ], $report->signCombinations);
    }

    // 6. BMI

    public function test_bmi_groups_use_each_patients_latest_bmi(): void
    {
        $becameNormal = Patient::factory()->create();
        $this->vitalSigns($becameNormal, '2026-01-10', 80.0);   // BMI 31.3
        $this->vitalSigns($becameNormal, '2026-08-10', 61.4);   // BMI 24.0, the latest

        $exactly25 = Patient::factory()->create();
        $this->vitalSigns($exactly25, '2026-05-01', 64.0);      // BMI 25.0: overweight

        $obese = Patient::factory()->create();
        $this->vitalSigns($obese, '2026-05-01', 90.0);          // BMI 35.2

        $underweight = Patient::factory()->create();
        $this->vitalSigns($underweight, '2026-05-01', 45.0);    // BMI 17.6

        $noVitals = Patient::factory()->create();

        foreach ([$becameNormal, $exactly25, $obese, $underweight, $noVitals] as $patient) {
            $this->alert('2026-03-01', $patient)->confirmed('2026-03-01', '2026-04-01')->create();
        }

        $this->assertSame([
            ['label' => 'Underweight', 'count' => 1, 'percent' => 20],
            ['label' => 'Normal', 'count' => 1, 'percent' => 20],
            ['label' => 'Overweight', 'count' => 1, 'percent' => 20],
            ['label' => 'Obese', 'count' => 1, 'percent' => 20],
            ['label' => 'No BMI recorded', 'count' => 1, 'percent' => 20],
        ], $this->report()->bmiGroups);
    }

    // 7. Referrals waiting too long

    public function test_referrals_with_no_result_after_six_weeks_are_listed_with_links(): void
    {
        $overdue = Patient::factory()->create(['first_name' => 'Thokozani', 'last_name' => 'Overdue']);
        $this->alert('2026-10-12', $overdue)->referred('2026-10-12')->create();   // 43 days ago

        $exactlySixWeeks = Patient::factory()->create(['first_name' => 'Thandiwe', 'last_name' => 'Sixweeks']);
        $this->alert('2026-10-13', $exactlySixWeeks)->referred('2026-10-13')->create();   // 42 days ago

        $hasResult = Patient::factory()->create(['first_name' => 'Tisunge', 'last_name' => 'Resulted']);
        $this->alert('2026-08-01', $hasResult)->confirmed('2026-08-01', '2026-10-01')->create();

        // Referred long before the chosen period: still listed.
        $longAgo = Patient::factory()->create(['first_name' => 'Lusungu', 'last_name' => 'Longago']);
        $this->alert('2025-06-01', $longAgo)->referred('2025-06-01')->create();

        $report = $this->report('?period=custom&from=2026-11-01&to=2026-11-24');
        $this->assertSame(['Lusungu Longago', 'Thokozani Overdue'], array_map(fn (array $row): string => $row['patient']->fullName(), $report->overdueReferrals));
        $this->assertSame(6, $report->overdueReferrals[1]['weeks']);

        $this->actingAs($this->doctor)
            ->get('/reports')
            ->assertSee('Referrals with no result after 6 weeks')
            ->assertSee('Thokozani Overdue')
            ->assertSee(route('patients.show', $overdue->id))
            ->assertSee($overdue->patient_number)
            ->assertDontSee('Thandiwe Sixweeks')
            ->assertDontSee('Tisunge Resulted');
    }

    public function test_the_waiting_limit_comes_from_the_config_file(): void
    {
        config(['screening.report.overdue_results_after_weeks' => 2]);

        $patient = Patient::factory()->create(['first_name' => 'Thandiwe', 'last_name' => 'Sixweeks']);
        $this->alert('2026-10-13', $patient)->referred('2026-10-13')->create();

        $this->actingAs($this->doctor)
            ->get('/reports')
            ->assertSee('Referrals with no result after 2 weeks')
            ->assertSee('Thandiwe Sixweeks');
    }

    public function test_only_the_waiting_list_shows_patient_names(): void
    {
        $diagnosed = Patient::factory()->create(['first_name' => 'Chisomo', 'last_name' => 'Nyirenda']);
        $this->alert('2026-03-01', $diagnosed)->confirmed('2026-03-01', '2026-04-01')->create();

        $this->actingAs($this->doctor)
            ->get('/reports')
            ->assertDontSee('Chisomo')
            ->assertDontSee('Nyirenda')
            ->assertSee('No referrals have been waiting more than 6 weeks.');
    }


    public function test_the_number_of_queries_does_not_grow_with_the_data(): void
    {
        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->doctor)->get('/reports')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $addPatients = function (int $count): void {
            foreach (range(1, $count) as $i) {
                $patient = Patient::factory()->create();
                $this->vitalSigns($patient, '2026-05-01', 70.0);
                $this->alert('2026-03-01', $patient)->confirmed('2026-03-01', '2026-04-01')->create();
                $this->alert('2026-03-01')->referred('2026-08-01')->create();
            }
        };

        $addPatients(2);
        $fewPatients = $countQueries();

        $addPatients(20);
        $this->assertSame($fewPatients, $countQueries());
    }
}
