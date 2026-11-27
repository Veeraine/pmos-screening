<?php

namespace Tests\Feature;

use App\Emr\ConsultationNotes;
use App\Emr\EmrAdapter;
use App\Emr\SimulatedMedix\SimulatedMedixAdapter;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Checks the simulated Medix adapter reads from the patients, visits, vital_signs and users tables.
 */
class SimulatedMedixAdapterTest extends TestCase
{
    use RefreshDatabase;

    private EmrAdapter $emr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
        $this->emr = app(EmrAdapter::class);
    }

    public function test_it_is_the_adapter_chosen_in_the_config(): void
    {
        $this->assertSame('simulated_medix', config('emr.adapter'));
        $this->assertInstanceOf(SimulatedMedixAdapter::class, $this->emr);
        $this->assertSame('Medix (simulated)', $this->emr->systemName());
    }

    public function test_it_finds_a_patient_with_their_details(): void
    {
        $nurse = User::factory()->role(Role::Nurse)->create(['name' => 'Nurse Kumwenda']);
        $patient = Patient::factory()->create([
            'first_name' => 'Grace', 'last_name' => 'Phiri', 'date_of_birth' => '1997-03-14',
            'phone' => '+265 991 100 001', 'address' => 'Chitawira, Blantyre', 'registered_by' => $nurse->id,
        ]);

        $found = $this->emr->findPatient((string) $patient->id);

        $this->assertSame((string) $patient->id, $found->id);
        $this->assertSame($patient->patient_number, $found->patientNumber);
        $this->assertSame('Grace Phiri', $found->fullName());
        $this->assertSame(29, $found->age());
        $this->assertSame('+265 991 100 001', $found->phone);
        $this->assertSame('Chitawira, Blantyre', $found->address);
        $this->assertSame('Nurse Kumwenda', $found->registeredByName);
        $this->assertNull($this->emr->findPatient('999999'));
    }

    public function test_it_finds_several_patients_at_once_keyed_by_id(): void
    {
        [$first, $second] = Patient::factory()->count(2)->create();

        $found = $this->emr->findPatients([(string) $first->id, (string) $second->id, '999999']);

        $this->assertCount(2, $found);
        $this->assertSame($first->first_name, $found->get((string) $first->id)->firstName);
        $this->assertSame($second->first_name, $found->get((string) $second->id)->firstName);
    }

    public function test_it_searches_patients(): void
    {
        Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda']);
        Patient::factory()->create(['first_name' => 'Tamara', 'last_name' => 'Gondwe']);

        $results = $this->emr->searchPatients('chikondi', 25);

        $this->assertSame(['Chikondi Banda'], collect($results->items())->map->fullName()->all());
        $this->assertSame(2, $this->emr->searchPatients('', 25)->total());
    }

    public function test_it_lists_a_patients_visits_newest_first_with_vitals_notes_and_staff(): void
    {
        $patient = Patient::factory()->create();
        $doctor = User::factory()->role(Role::Doctor)->create(['name' => 'Dr Mwale']);
        $older = Visit::factory()->for($patient)->daysAgo(30)->create();
        $newer = Visit::factory()->for($patient)->daysAgo(2)->create([
            'notes' => 'Pimples on her chin.', 'menstrual_history' => 'Cycles of 45 days.', 'diagnosis' => 'Acne',
            'consulted_by' => $doctor->id, 'consulted_at' => now()->subDays(2),
        ]);
        VitalSign::factory()->for($newer)->create(['weight_kg' => 70, 'height_cm' => 160, 'systolic_bp' => 120, 'diastolic_bp' => 80]);

        $visits = $this->emr->visitsForPatient((string) $patient->id);

        $this->assertSame([(string) $newer->id, (string) $older->id], $visits->pluck('id')->all());
        $this->assertSame('Pimples on her chin.', $visits[0]->notes);
        $this->assertSame('Cycles of 45 days.', $visits[0]->menstrualHistory);
        $this->assertSame('Acne', $visits[0]->diagnosis);
        $this->assertSame('Dr Mwale', $visits[0]->consultedByName);
        $this->assertTrue($visits[0]->isConsulted());
        $this->assertSame('120/80', $visits[0]->vitalSigns->bloodPressure());
        $this->assertSame('27.3', $visits[0]->vitalSigns->bmi);
        $this->assertNull($visits[1]->vitalSigns);
        $this->assertFalse($visits[1]->isConsulted());
    }

    public function test_it_lists_todays_visits_in_arrival_order(): void
    {
        $first = Visit::factory()->create();
        $second = Visit::factory()->create();
        Visit::factory()->daysAgo(1)->create();

        $this->assertSame([(string) $first->id, (string) $second->id], $this->emr->todaysVisits()->pluck('id')->all());
    }

    public function test_it_saves_the_doctors_notes(): void
    {
        $visit = Visit::factory()->create();
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->emr->saveConsultationNotes((string) $visit->id, new ConsultationNotes('Headache.', 'Regular cycles.', 'Migraine'), (string) $doctor->id);

        $visit->refresh();
        $this->assertSame('Headache.', $visit->notes);
        $this->assertSame('Regular cycles.', $visit->menstrual_history);
        $this->assertSame('Migraine', $visit->diagnosis);
        $this->assertSame($doctor->id, $visit->consulted_by);
        $this->assertTrue($visit->isConsulted());
    }

    public function test_it_gives_staff_names_by_id(): void
    {
        $doctor = User::factory()->create(['name' => 'Dr Mwale']);

        $this->assertSame([(string) $doctor->id => 'Dr Mwale'], $this->emr->staffNames([(string) $doctor->id, '999999']));
        $this->assertSame([], $this->emr->staffNames([]));
    }

    public function test_logins_are_checked_by_the_emr(): void
    {
        $doctor = User::factory()->role(Role::Doctor)->create(['email' => 'doctor@chitawira.test']);

        $this->assertTrue(Auth::validate(['email' => 'doctor@chitawira.test', 'password' => 'password']));
        $this->assertFalse(Auth::validate(['email' => 'doctor@chitawira.test', 'password' => 'wrong']));
        $this->assertSame((string) $doctor->id, Auth::getProvider()->retrieveById($doctor->id)->staffId());
    }
}
