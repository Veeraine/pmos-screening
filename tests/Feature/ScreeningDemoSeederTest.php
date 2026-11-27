<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Patient;
use App\Models\User;
use App\Role;
use App\Screening\FeatureFinding;
use App\ScreeningOutcome;
use Database\Seeders\ScreeningDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ScreeningDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->role(Role::Nurse)->create();
        User::factory()->role(Role::Doctor)->create();
        $this->seed(ScreeningDemoSeeder::class);
    }

    /**
     * For each found feature: its key and the dates of the visits it came from.
     *
     * @return array<string, list<string>>
     */
    private function foundFeaturesFor(string $firstName, string $lastName): array
    {
        $patient = Patient::where('first_name', $firstName)->where('last_name', $lastName)->sole();

        return collect($this->screeningCheck($patient)->foundFeatures())
            ->mapWithKeys(fn (FeatureFinding $feature): array => [
                $feature->key => array_map(fn (array $item): string => $item['visit']->date->toDateString(), $feature->evidence),
            ])
            ->all();
    }

    public function test_grace_phiri_has_two_symptoms_of_the_same_feature_so_one_of_three(): void
    {
        $this->assertSame(
            ['irregular_ovulation' => ['2026-10-06', '2026-05-12']],
            $this->foundFeaturesFor('Grace', 'Phiri'),
        );
    }

    public function test_tiwonge_banda_has_different_features_from_different_visits_so_two_of_three(): void
    {
        $this->assertSame(
            ['irregular_ovulation' => ['2026-05-20'], 'high_androgens' => ['2026-10-07']],
            $this->foundFeaturesFor('Tiwonge', 'Banda'),
        );
    }

    public function test_chisomo_mwale_has_two_features_with_an_unrelated_malaria_visit_in_between(): void
    {
        $this->assertSame(
            ['high_androgens' => ['2026-03-10'], 'polycystic_ovaries' => ['2026-09-22']],
            $this->foundFeaturesFor('Chisomo', 'Mwale'),
        );

        $malariaVisit = Patient::where('first_name', 'Chisomo')->sole()->visits()->where('diagnosis', 'Malaria')->sole();
        $this->assertCount(0, $this->confirmedSymptomsAt($malariaVisit));
    }

    public function test_bmi_at_later_visits_uses_the_height_from_the_first_visit(): void
    {
        $octoberVitals = Patient::where('first_name', 'Grace')->sole()
            ->visits()->whereDate('visit_date', '2026-10-06')->sole()->vitalSigns;

        $this->assertNull($octoberVitals->height_cm);
        $this->assertSame('23.6', $octoberVitals->bmi);
    }

    public function test_running_the_seeder_again_does_not_create_duplicates(): void
    {
        $this->seed(ScreeningDemoSeeder::class);

        $this->assertSame(6, Patient::count());
        $this->assertSame(6, Alert::count());
    }

    private function alertsOfPatientNamed(string $firstName): Collection
    {
        return $this->alertsFor(Patient::where('first_name', $firstName)->sole())->orderBy('id')->get();
    }

    public function test_grace_phiri_has_no_alert_because_only_one_feature_was_found(): void
    {
        $this->assertCount(0, $this->alertsOfPatientNamed('Grace'));
    }

    public function test_tiwonge_and_chisomo_each_have_one_open_alert_raised_at_their_latest_visit(): void
    {
        foreach (['Tiwonge' => '2026-10-07', 'Chisomo' => '2026-09-22'] as $firstName => $raisedOn) {
            $alerts = $this->alertsOfPatientNamed($firstName);

            $this->assertCount(1, $alerts);
            $this->assertTrue($alerts[0]->isOpen());
            $this->assertSame($raisedOn, $alerts[0]->created_at->toDateString());
            $this->assertSame(2, $alerts[0]->featureCount());
        }
    }

    public function test_thandiwe_chirwa_was_ruled_out_then_got_a_new_alert_that_mentions_it(): void
    {
        [$first, $second] = $this->alertsOfPatientNamed('Thandiwe');

        $this->assertSame(ScreeningOutcome::RuledOut, $first->outcome);
        $this->assertSame('2026-04-21', $first->outcome_date->toDateString());
        $this->assertTrue($second->isOpen());
        $this->assertSame($first->id, $second->previous_alert_id);

        $ovaries = collect($second->findings)->firstWhere('key', 'polycystic_ovaries');
        $this->assertTrue($ovaries['evidence'][0]['is_new']);
    }

    public function test_mphatso_kamanga_is_diagnosed_and_later_acne_raised_no_new_alert(): void
    {
        $alerts = $this->alertsOfPatientNamed('Mphatso');

        $this->assertCount(1, $alerts);
        $this->assertSame(ScreeningOutcome::Confirmed, $alerts[0]->outcome);
        $this->assertSame('2026-03-02', $alerts[0]->outcome_date->toDateString());
    }

    public function test_lusungu_nyirenda_is_referred_and_awaiting_results(): void
    {
        $alerts = $this->alertsOfPatientNamed('Lusungu');

        $this->assertCount(1, $alerts);
        $this->assertTrue($alerts[0]->isAwaitingResults());
    }

    public function test_an_existing_demo_patient_without_alerts_gets_its_alert_check(): void
    {
        $this->alertsFor(Patient::where('first_name', 'Tiwonge')->sole())->delete();

        $this->seed(ScreeningDemoSeeder::class);

        $this->assertCount(1, $this->alertsOfPatientNamed('Tiwonge'));
    }
}
