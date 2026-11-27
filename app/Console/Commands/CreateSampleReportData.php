<?php

namespace App\Console\Commands;

use App\AlertStatus;
use App\Models\Alert;
use App\Models\AlertCorrection;
use App\Models\ConfirmedSymptom;
use App\Models\Patient;
use App\Models\Symptom;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use App\Role;
use App\Screening\AlertRaiser;
use App\ScreeningOutcome;
use Carbon\CarbonInterface;
use Database\Factories\PatientFactory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Makes about a year of fake patients, visits and alerts so the statistics report has numbers to show.
 * The real alert rules are used. Sample accounts are deactivated, and --fresh only deletes their patients.
 */
#[Signature('pmos:sample-report-data
    {--patients=150 : How many sample patients to create}
    {--seed= : A number that makes the same sample data every time}
    {--fresh : Delete the sample patients made earlier and start again}
    {--force : Do not ask for confirmation on a live server}')]
#[Description('Create about a year of sample patients, alerts and results for the statistics report')]
class CreateSampleReportData extends Command
{
    /**
     * The accounts that register and see the sample patients.
     *
     * @var array<string, array{name: string, email: string}>
     */
    public const ACCOUNTS = [
        'nurse' => ['name' => 'Sample Nurse', 'email' => 'sample.nurse@chitawira.test'],
        'doctor' => ['name' => 'Sample Doctor', 'email' => 'sample.doctor@chitawira.test'],
    ];

    /**
     * Symptoms for each Rotterdam sign, as [symptom code, words in the notes, reason for the visit].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string}>>
     */
    private const SIGN_SYMPTOMS = [
        'irregular_ovulation' => [
            ['irregular_periods', 'periods come every two to three months', 'Irregular periods'],
            ['irregular_periods', 'irregular cycles for the past year', 'Irregular periods'],
            ['absent_periods', 'no period for 4 months', 'Missed periods'],
            ['prolonged_bleeding', 'heavy periods lasting over a week', 'Heavy periods'],
        ],
        'high_androgens' => [
            ['excess_hair_growth', 'coarse hair growing on her chin and upper lip', 'Unwanted hair'],
            ['acne', 'pimples on her forehead and chin', 'Skin problem'],
            ['hair_thinning', 'hair thinning at the crown', 'Hair loss'],
        ],
        'polycystic_ovaries' => [
            ['polycystic_ovaries_on_ultrasound', 'ultrasound shows multiple small follicles in both ovaries', 'Ultrasound results'],
        ],
    ];

    /**
     * Visits for other illnesses, as [reason, notes, diagnosis].
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const OTHER_VISITS = [
        ['Fever', 'Fever, chills and headache for 2 days. Malaria rapid test positive.', 'Malaria'],
        ['Cough', 'Dry cough and runny nose for 4 days. Chest clear.', 'Upper respiratory tract infection'],
        ['Painful urination', 'Burning when passing urine for 2 days. Urine dipstick shows nitrites.', 'Urinary tract infection'],
        ['Headache', 'Headache across the forehead for a week, worse when tired. No visual changes.', 'Tension headache'],
        ['Abdominal pain', 'Upper abdominal pain after meals for two weeks. No vomiting.', 'Gastritis'],
        ['Sore throat', 'Sore throat and painful swallowing for 3 days. Tonsils red and swollen.', 'Tonsillitis'],
        ['Routine check-up', 'Routine review. Feeling well, no complaints.', 'Well, no new problems'],
        ['Back pain', 'Lower back pain after lifting at work. No numbness.', 'Mechanical back pain'],
    ];

    /**
     * Reasons doctors give for not referring.
     */
    private const NOT_REFERRED_REASONS = [
        'Symptoms explained by another condition',
        'Patient is pregnant',
        'Patient declined referral',
        'Already under the care of a gynaecologist',
    ];

    /**
     * Ages of the sample patients and how common each range is.
     *
     * @var list<array{0: int, 1: int, 2: int}>
     */
    private const AGE_RANGES = [[16, 19, 8], [20, 24, 22], [25, 29, 28], [30, 34, 21], [35, 39, 13], [40, 46, 8]];

    private Randomizer $random;

    /**
     * The real current time, put back after each visit is replayed.
     */
    private ?CarbonInterface $testNow;

    /** @var array<string, int> */
    private array $symptomIds;

    /** @var array<string, int> */
    private array $created = ['patients' => 0, 'visits' => 0, 'alerts' => 0, 'referred' => 0, 'confirmed' => 0, 'ruled_out' => 0, 'awaiting' => 0];

    /**
     * Execute the console command.
     */
    public function handle(AlertRaiser $alertRaiser): int
    {
        $count = (int) $this->option('patients');

        if ($count < 1) {
            $this->error('--patients must be at least 1.');

            return self::FAILURE;
        }

        if ($this->laravel->isProduction() && ! $this->option('force')
            && ! $this->confirm('This is a live server. Add made-up patients to it?')) {
            return self::FAILURE;
        }

        $nurse = $this->account(Role::Nurse);
        $doctor = $this->account(Role::Doctor);

        if (Patient::where('registered_by', $nurse->id)->exists()) {
            if (! $this->option('fresh')) {
                $this->error('Sample data has already been added. Run with --fresh to delete it and start again.');

                return self::FAILURE;
            }

            $this->deleteSampleData($nurse);
        }

        $seed = $this->option('seed');
        $this->random = new Randomizer(new Mt19937($seed === null ? random_int(1, PHP_INT_MAX) : (int) $seed));
        $this->symptomIds = Symptom::pluck('id', 'code')->all();
        $this->testNow = Carbon::getTestNow();

        try {
            DB::transaction(function () use ($count, $nurse, $doctor, $alertRaiser): void {
                for ($i = 0; $i < $count; $i++) {
                    $this->createPatient($nurse, $doctor, $alertRaiser);
                }
            });
        } finally {
            Carbon::setTestNow($this->testNow);
        }

        $this->table(['Created', 'Number'], collect($this->created)->map(fn (int $number, string $what): array => [Str::headline($what), $number])->values()->all());

        return self::SUCCESS;
    }

    /**
     * Gets the sample account, or creates it (deactivated).
     */
    private function account(Role $role): User
    {
        $details = self::ACCOUNTS[$role->value];
        $user = User::firstOrNew(['email' => $details['email']]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $details['name'],
                'role' => $role,
                'password' => Str::random(40),
                'is_active' => false,
            ])->save();
        }

        return $user;
    }

    /**
     * Deletes the sample nurse's patients and everything linked to them.
     */
    private function deleteSampleData(User $nurse): void
    {
        $patientIds = Patient::where('registered_by', $nurse->id)->pluck('id');
        $emrPatientIds = $patientIds->map(fn (int $id): string => (string) $id);

        DB::transaction(function () use ($patientIds, $emrPatientIds): void {
            $alertIds = Alert::whereIn('emr_patient_id', $emrPatientIds)->pluck('id');

            AlertCorrection::whereIn('alert_id', $alertIds)->delete();
            Alert::whereIn('id', $alertIds)->update(['previous_alert_id' => null]);
            Alert::whereIn('id', $alertIds)->delete();
            ConfirmedSymptom::whereIn('emr_patient_id', $emrPatientIds)->delete();
            VitalSign::whereIn('visit_id', Visit::select('id')->whereIn('patient_id', $patientIds))->delete();
            Visit::whereIn('patient_id', $patientIds)->delete();
            Patient::whereKey($patientIds)->delete();
        });

        $this->info("Deleted {$patientIds->count()} sample patients made earlier.");
    }

    /**
     * Creates one patient and their visits. About 6 in 10 end up with an alert.
     */
    private function createPatient(User $nurse, User $doctor, AlertRaiser $alertRaiser): void
    {
        $screening = $this->chance(62);
        $firstVisit = today()->subDays($this->random->getInt($screening ? 21 : 1, 365));
        $plan = $screening ? $this->screeningVisits($firstVisit) : $this->otherVisits($firstVisit);

        $patient = new Patient([
            'first_name' => $this->pick(PatientFactory::FIRST_NAMES),
            'last_name' => $this->pick(PatientFactory::LAST_NAMES),
            'date_of_birth' => $this->dateOfBirth()->toDateString(),
            'phone' => sprintf('+265 %d %03d %03d', $this->pick([991, 995, 888, 884]), $this->random->getInt(0, 999), $this->random->getInt(0, 999)),
            'address' => $this->pick(PatientFactory::ADDRESSES),
        ]);
        $patient->registered_by = $nurse->id;
        $patient->created_at = $plan[0]['date']->copy()->setTime(8, 10);
        $patient->save();
        $this->created['patients']++;

        $heightCm = $this->random->getInt(300, 350) / 2;
        $bmi = $this->normal($screening ? 28 : 24, $screening ? 5 : 4, 17, 42);

        foreach ($plan as $details) {
            $visit = $this->createVisit($patient, $details, $nurse, $doctor, $heightCm, $bmi);

            if ($details['symptoms'] === []) {
                continue;
            }

            Carbon::setTestNow($visit->consulted_at);
            $alert = $alertRaiser->raiseIfNeeded((string) $patient->id, (string) $visit->id);
            Carbon::setTestNow($this->testNow);

            if ($alert) {
                $this->decide($alert, $doctor, $visit->consulted_at->copy()->addMinutes(5));
            }
        }
    }

    /**
     * Visits that lead to an alert, with the signs spread over visits a few weeks apart.
     *
     * @return list<array{date: Carbon, reason: string, notes: string, diagnosis: string, symptoms: list<array{0: string, 1: string, 2: string}>}>
     */
    private function screeningVisits(Carbon $firstVisit): array
    {
        $signs = ['irregular_ovulation', $this->chance(70) ? 'high_androgens' : 'polycystic_ovaries'];

        if ($this->chance(15)) {
            $signs = array_keys(self::SIGN_SYMPTOMS);
        }

        $daysLeft = (int) $firstVisit->diffInDays(today());
        $visits = [];

        if ($this->chance(40)) {
            $visits[] = $this->otherVisit($firstVisit->copy()->subDays($this->random->getInt(20, 200)));
        }

        if ($this->chance(20) || $daysLeft < 14) {
            $visits[] = $this->symptomVisit($firstVisit, $signs);
        } else {
            $later = $firstVisit->copy()->addDays($this->random->getInt(14, min(120, $daysLeft)));
            $visits[] = $this->symptomVisit($firstVisit, [$signs[0]]);

            if ($this->chance(30) && $firstVisit->diffInDays($later) > 10) {
                $visits[] = $this->otherVisit($firstVisit->copy()->addDays(5));
            }

            $visits[] = $this->symptomVisit($later, array_slice($signs, 1));
        }

        return $visits;
    }

    /**
     * 1 to 3 visits for other illnesses, sometimes with one PMOS symptom (not enough for an alert).
     *
     * @return list<array{date: Carbon, reason: string, notes: string, diagnosis: string, symptoms: list<array{0: string, 1: string, 2: string}>}>
     */
    private function otherVisits(Carbon $firstVisit): array
    {
        $visits = [];
        $date = $firstVisit->copy();

        foreach (range(1, $this->random->getInt(1, 3)) as $index) {
            if ($date->gt(today())) {
                break;
            }

            $visits[] = $index === 1 && $this->chance(25)
                ? $this->symptomVisit($date->copy(), [$this->pick(array_keys(self::SIGN_SYMPTOMS))])
                : $this->otherVisit($date->copy());
            $date->addDays($this->random->getInt(30, 150));
        }

        return $visits;
    }

    /**
     * @param  list<string>  $signs
     * @return array{date: Carbon, reason: string, notes: string, diagnosis: string, symptoms: list<array{0: string, 1: string, 2: string}>}
     */
    private function symptomVisit(Carbon $date, array $signs): array
    {
        $symptoms = array_map(fn (string $sign): array => $this->pick(self::SIGN_SYMPTOMS[$sign]), $signs);

        return [
            'date' => $date,
            'reason' => $symptoms[0][2],
            'notes' => Str::ucfirst(implode('. ', array_map(fn (array $symptom): string => Str::ucfirst($symptom[1]), $symptoms))).'.',
            'diagnosis' => 'Under investigation',
            'symptoms' => $symptoms,
        ];
    }

    /**
     * @return array{date: Carbon, reason: string, notes: string, diagnosis: string, symptoms: list<array{0: string, 1: string, 2: string}>}
     */
    private function otherVisit(Carbon $date): array
    {
        [$reason, $notes, $diagnosis] = $this->pick(self::OTHER_VISITS);

        return ['date' => $date, 'reason' => $reason, 'notes' => $notes, 'diagnosis' => $diagnosis, 'symptoms' => []];
    }

    /**
     * Saves a visit with vital signs, the consultation and confirmed symptoms.
     *
     * @param  array{date: Carbon, reason: string, notes: string, diagnosis: string, symptoms: list<array{0: string, 1: string, 2: string}>}  $details
     */
    private function createVisit(Patient $patient, array $details, User $nurse, User $doctor, float $heightCm, float $bmi): Visit
    {
        $checkedInAt = $details['date']->copy()->setTime($this->random->getInt(8, 14), $this->random->getInt(0, 59));
        $consultedAt = $checkedInAt->copy()->addMinutes($this->random->getInt(20, 90));

        $visit = $patient->visits()->make([
            'visit_date' => $details['date']->toDateString(),
            'reason' => $details['reason'],
            'notes' => $details['notes'],
            'diagnosis' => $details['diagnosis'],
        ]);
        $visit->checked_in_by = $nurse->id;
        $visit->consulted_by = $doctor->id;
        $visit->consulted_at = $consultedAt;
        $visit->created_at = $checkedInAt;
        $visit->save();
        $this->created['visits']++;

        $weightKg = round($bmi * ($heightCm / 100) ** 2 + $this->random->getInt(-15, 15) / 10, 1);

        $vitalSigns = $visit->vitalSigns()->make([
            'weight_kg' => $weightKg,
            'height_cm' => $heightCm,
            'systolic_bp' => $this->random->getInt(105, 135),
            'diastolic_bp' => $this->random->getInt(65, 88),
            'temperature_c' => $this->random->getInt(361, 374) / 10,
            'pulse_rate' => $this->random->getInt(62, 96),
        ]);
        $vitalSigns->recorded_by = $nurse->id;
        $vitalSigns->created_at = $checkedInAt->copy()->addMinutes(10);
        $vitalSigns->save();

        foreach ($details['symptoms'] as [$code, $words]) {
            $confirmed = new ConfirmedSymptom;
            $confirmed->forceFill([
                'emr_patient_id' => (string) $patient->id,
                'emr_visit_id' => (string) $visit->id,
                'symptom_id' => $this->symptomIds[$code],
                'source' => ConfirmedSymptom::SOURCE_DETECTED,
                'matched_text' => $words,
                'confirmed_by' => (string) $doctor->id,
                'created_at' => $consultedAt,
                'updated_at' => $consultedAt,
            ])->save();
        }

        return $visit;
    }

    /**
     * The doctor's decision, and for most referrals a result two to eight weeks later.
     */
    private function decide(Alert $alert, User $doctor, Carbon $decidedAt): void
    {
        $this->created['alerts']++;
        $referred = $this->chance(85);

        $alert->forceFill([
            'status' => AlertStatus::Closed,
            'referred' => $referred,
            'not_referred_reason' => $referred ? null : $this->pick(self::NOT_REFERRED_REASONS),
            'decided_by' => $doctor->staffId(),
            'decided_at' => $decidedAt,
        ]);

        if ($referred) {
            $this->created['referred']++;
            $resultDate = $decidedAt->copy()->startOfDay()->addDays($this->random->getInt(14, 56));

            // Only referrals from the last 8 weeks may still be waiting for a result.
            $recent = $decidedAt->gte(today()->subWeeks(8));

            if ($resultDate->lte(today()) && (! $recent || $this->chance(75))) {
                $outcome = $this->chance(60) ? ScreeningOutcome::Confirmed : ScreeningOutcome::RuledOut;
                $alert->forceFill([
                    'outcome' => $outcome,
                    'outcome_date' => $resultDate->toDateString(),
                    'outcome_recorded_by' => $doctor->staffId(),
                    'outcome_recorded_at' => $resultDate->copy()->setTime(11, 30),
                ]);
                $this->created[$outcome === ScreeningOutcome::Confirmed ? 'confirmed' : 'ruled_out']++;
            } else {
                $this->created['awaiting']++;
            }
        }

        $alert->save();
    }

    /**
     * Random date of birth using AGE_RANGES.
     */
    private function dateOfBirth(): Carbon
    {
        $roll = $this->random->getInt(1, array_sum(array_column(self::AGE_RANGES, 2)));

        foreach (self::AGE_RANGES as [$youngest, $oldest, $weight]) {
            if ($roll <= $weight) {
                return today()->subYears($this->random->getInt($youngest, $oldest))->subDays($this->random->getInt(0, 364));
            }

            $roll -= $weight;
        }

        return today()->subYears(25);
    }

    /**
     * True about $percent times in a hundred.
     */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->pickArrayKeys($items, 1)[0]];
    }

    /**
     * A number from a bell curve around $mean, kept between $min and $max.
     */
    private function normal(float $mean, float $spread, float $min, float $max): float
    {
        $value = $mean + $spread * sqrt(-2 * log(1 - $this->random->nextFloat())) * cos(2 * M_PI * $this->random->nextFloat());

        return max($min, min($max, $value));
    }
}
