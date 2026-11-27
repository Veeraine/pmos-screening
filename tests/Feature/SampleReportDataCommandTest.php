<?php

namespace Tests\Feature;

use App\AlertStatus;
use App\Console\Commands\CreateSampleReportData;
use App\Models\Alert;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Reports\ScreeningReport;
use App\Role;
use App\ScreeningOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleReportDataCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 0));
    }

    private function sampleNurse(): User
    {
        return User::where('email', CreateSampleReportData::ACCOUNTS['nurse']['email'])->sole();
    }

    public function test_it_creates_a_year_of_patients_alerts_and_results(): void
    {
        $this->artisan('pmos:sample-report-data', ['--patients' => 80, '--seed' => 7])->assertSuccessful();

        $this->assertSame(80, Patient::where('registered_by', $this->sampleNurse()->id)->count());
        $this->assertGreaterThan(30, Alert::count());
        $this->assertGreaterThan(5, Alert::where('outcome', ScreeningOutcome::Confirmed)->count());
        $this->assertGreaterThan(3, Alert::where('outcome', ScreeningOutcome::RuledOut)->count());
        $this->assertGreaterThan(0, Alert::where('referred', false)->count());
        $this->assertSame(0, Alert::where('status', AlertStatus::Open)->count());

        $this->assertFalse(Visit::where('visit_date', '>', today()->toDateString())->exists());
        $this->assertFalse(Alert::where('outcome_date', '>', today()->toDateString())->exists());
        $this->assertTrue(Alert::where('created_at', '<', today()->subMonths(10))->exists());
    }

    public function test_alerts_are_raised_by_the_real_screening_rules(): void
    {
        $this->artisan('pmos:sample-report-data', ['--patients' => 40, '--seed' => 7])->assertSuccessful();

        foreach (Alert::all() as $alert) {
            $this->assertGreaterThanOrEqual(config('screening.alert_threshold'), $alert->featureCount());
            $this->assertTrue(Visit::whereKey($alert->raised_at_emr_visit_id)->where('patient_id', $alert->emr_patient_id)->exists());
        }
    }

    public function test_the_report_has_data_in_every_section(): void
    {
        $this->artisan('pmos:sample-report-data', ['--seed' => 7])->assertSuccessful();

        /** @var ScreeningReport $report */
        $report = $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/reports')
            ->assertOk()
            ->viewData('report');

        $this->assertGreaterThan(0, $report->summary['raised']);
        $this->assertGreaterThan(0, $report->summary['awaiting']);
        $this->assertGreaterThan(2, count(array_filter(array_column($report->ageGroups, 'count'))));
        $this->assertGreaterThan(6, count(array_filter(array_column($report->diagnosesByMonth, 'count'))));
        $this->assertGreaterThan(1, count(array_filter(array_column($report->bmiGroups, 'count'))));
        $this->assertNotEmpty($report->overdueReferrals);
        $this->assertNotNull($report->confirmedPercent);
    }

    public function test_the_sample_accounts_cannot_log_in(): void
    {
        $this->artisan('pmos:sample-report-data', ['--patients' => 5, '--seed' => 7])->assertSuccessful();

        foreach (CreateSampleReportData::ACCOUNTS as $details) {
            $this->assertFalse(User::where('email', $details['email'])->sole()->is_active);
        }

        $this->assertSame(Role::Doctor, User::where('email', CreateSampleReportData::ACCOUNTS['doctor']['email'])->sole()->role);
    }

    public function test_it_will_not_add_sample_data_twice(): void
    {
        $this->artisan('pmos:sample-report-data', ['--patients' => 5, '--seed' => 7])->assertSuccessful();

        $this->artisan('pmos:sample-report-data', ['--patients' => 5])
            ->expectsOutputToContain('Run with --fresh')
            ->assertFailed();

        $this->assertSame(5, Patient::count());
    }

    public function test_fresh_replaces_only_the_sample_data(): void
    {
        $realAlert = Alert::factory()->confirmed('2026-03-01', '2026-04-01')->create();
        $this->artisan('pmos:sample-report-data', ['--patients' => 30, '--seed' => 7])->assertSuccessful();
        $sampleAlerts = Alert::whereKeyNot($realAlert->id)->count();

        $this->artisan('pmos:sample-report-data', ['--patients' => 30, '--seed' => 7, '--fresh' => true])->assertSuccessful();

        $this->assertSame(30, Patient::where('registered_by', $this->sampleNurse()->id)->count());
        $this->assertSame(31, Patient::count());
        $this->assertTrue($realAlert->fresh()->exists);
        $this->assertSame($sampleAlerts, Alert::whereKeyNot($realAlert->id)->count(), 'The same seed makes the same data.');
    }

    public function test_it_asks_before_adding_data_to_a_live_server(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('pmos:sample-report-data', ['--patients' => 5])
            ->expectsConfirmation('This is a live server. Add made-up patients to it?', 'no')
            ->assertFailed();

        $this->assertSame(0, Patient::count());
    }
}
