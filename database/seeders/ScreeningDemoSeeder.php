<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\ConfirmedSymptom;
use App\Models\Patient;
use App\Models\Symptom;
use App\Models\User;
use App\Models\Visit;
use App\Role;
use App\Screening\AlertRaiser;
use App\Screening\SymptomDetector;
use App\ScreeningOutcome;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample patients with visits on different dates, for trying the screening and alerts.
 * Uses the real detector and alert rules. Safe to run again, existing patients are skipped.
 */
class ScreeningDemoSeeder extends Seeder
{
    public function __construct(
        private SymptomDetector $detector,
        private AlertRaiser $alertRaiser,
    ) {}

    public function run(): void
    {
        $nurse = User::where('role', Role::Nurse)->firstOrFail();
        $doctor = User::where('role', Role::Doctor)->firstOrFail();
        $symptomIdsByCode = Symptom::pluck('id', 'code');

        try {
            foreach ($this->demoPatients() as $demo) {
                $existing = Patient::possibleDuplicatesOf($demo['patient'])->first();

                if ($existing) {
                    $this->checkExistingPatientForAlerts($existing);

                    continue;
                }

                DB::transaction(fn () => $this->createPatientWithHistory($demo, $nurse, $doctor, $symptomIdsByCode));
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Create a demo patient and replay their visits in date order.
     *
     * @param  array{patient: array<string, string>, height_cm: float, visits: list<array<string, mixed>>}  $demo
     * @param  Collection<string, int>  $symptomIdsByCode
     */
    private function createPatientWithHistory(array $demo, User $nurse, User $doctor, Collection $symptomIdsByCode): void
    {
        $patient = new Patient($demo['patient']);
        $patient->registered_by = $nurse->id;
        $patient->created_at = Carbon::parse($demo['visits'][0]['date'])->setTime(8, 15);
        $patient->save();

        foreach ($demo['visits'] as $index => $visitDetails) {
            $checkedInAt = Carbon::parse($visitDetails['date'])->setTime(8, 30);
            $consultedAt = $checkedInAt->copy()->addMinutes(40);

            $visit = $patient->visits()->make([
                'visit_date' => $visitDetails['date'],
                'reason' => $visitDetails['reason'],
                'notes' => $visitDetails['notes'],
                'menstrual_history' => $visitDetails['menstrual_history'] ?? null,
                'diagnosis' => $visitDetails['diagnosis'],
            ]);
            $visit->checked_in_by = $nurse->id;
            $visit->consulted_by = $doctor->id;
            $visit->consulted_at = $consultedAt;
            $visit->created_at = $checkedInAt;
            $visit->save();

            $vitalSigns = $visit->vitalSigns()->make([
                ...$visitDetails['vitals'],
                'height_cm' => $index === 0 ? $demo['height_cm'] : null,
            ]);
            $vitalSigns->recorded_by = $nurse->id;
            $vitalSigns->created_at = $checkedInAt->copy()->addMinutes(10);
            $vitalSigns->save();

            foreach ($this->detector->detect(SymptomDetector::combine($visit->notes, $visit->menstrual_history)) as $symptom) {
                $confirmed = new ConfirmedSymptom;
                $confirmed->forceFill([
                    'emr_patient_id' => (string) $patient->id,
                    'emr_visit_id' => (string) $visit->id,
                    'symptom_id' => $symptomIdsByCode[$symptom->code],
                    'source' => ConfirmedSymptom::SOURCE_DETECTED,
                    'matched_text' => Str::limit($symptom->excerpt, 250),
                    'confirmed_by' => (string) $doctor->id,
                    'created_at' => $consultedAt,
                    'updated_at' => $consultedAt,
                ])->save();
            }

            Carbon::setTestNow($consultedAt);
            $alert = $this->alertRaiser->raiseIfNeeded((string) $patient->id, (string) $visit->id);
            Carbon::setTestNow();

            if ($alert && isset($visitDetails['referred_on'])) {
                $this->recordReferral($alert, $doctor, $visitDetails['referred_on']);
            }

            if ($alert && isset($visitDetails['result'])) {
                $this->recordResult($alert, $doctor, ...$visitDetails['result']);
            }
        }
    }

    private function checkExistingPatientForAlerts(Patient $patient): void
    {
        if (Alert::forPatient((string) $patient->id)->exists()) {
            return;
        }

        $latestVisit = $patient->visits()->whereNotNull('consulted_at')->latest('visit_date')->first();

        if ($latestVisit instanceof Visit) {
            Carbon::setTestNow($latestVisit->consulted_at);
            $this->alertRaiser->raiseIfNeeded((string) $patient->id, (string) $latestVisit->id);
            Carbon::setTestNow();
        }
    }

    private function recordReferral(Alert $alert, User $doctor, string $date): void
    {
        $alert->forceFill([
            'status' => 'closed',
            'referred' => true,
            'decided_by' => $doctor->id,
            'decided_at' => Carbon::parse($date)->setTime(9, 30),
        ])->save();
    }

    private function recordResult(Alert $alert, User $doctor, string $outcome, string $date): void
    {
        $alert->forceFill([
            'outcome' => ScreeningOutcome::from($outcome),
            'outcome_date' => $date,
            'outcome_recorded_by' => $doctor->id,
            'outcome_recorded_at' => Carbon::parse($date)->setTime(14, 0),
        ])->save();
    }

    /**
     * The demo cases.
     *
     * @return list<array{patient: array<string, string>, height_cm: float, visits: list<array<string, mixed>>}>
     */
    private function demoPatients(): array
    {
        return [
            // Case 1: two symptoms of the SAME feature at different visits -> 1 of 3, no alert.
            [
                'patient' => ['first_name' => 'Grace', 'last_name' => 'Phiri', 'date_of_birth' => '1997-03-14', 'phone' => '+265 991 100 001', 'address' => 'Chitawira, Blantyre'],
                'height_cm' => 162.0,
                'visits' => [
                    [
                        'date' => '2026-05-12', 'reason' => 'Heavy bleeding',
                        'notes' => 'Bleeding for 3 weeks this cycle. Feels tired and dizzy.',
                        'menstrual_history' => 'Cycles usually 30 days.',
                        'diagnosis' => 'Anaemia, under investigation',
                        'vitals' => ['weight_kg' => 61.5, 'systolic_bp' => 108, 'diastolic_bp' => 68, 'temperature_c' => 36.6, 'pulse_rate' => 96],
                    ],
                    [
                        'date' => '2026-10-06', 'reason' => 'Follow-up',
                        'notes' => 'No period since July. Pregnancy test negative.',
                        'diagnosis' => 'Under investigation',
                        'vitals' => ['weight_kg' => 62.0, 'systolic_bp' => 112, 'diastolic_bp' => 72, 'temperature_c' => 36.8, 'pulse_rate' => 80],
                    ],
                ],
            ],

            // Case 2: DIFFERENT features at different visits -> 2 of 3, open alert.
            [
                'patient' => ['first_name' => 'Tiwonge', 'last_name' => 'Banda', 'date_of_birth' => '2000-08-02', 'phone' => '+265 888 200 002', 'address' => 'Ndirande, Blantyre'],
                'height_cm' => 158.0,
                'visits' => [
                    [
                        'date' => '2026-05-20', 'reason' => 'Missed periods',
                        'notes' => "Hasn't had her period in 3 months, not pregnant.",
                        'diagnosis' => 'Amenorrhoea, cause unclear',
                        'vitals' => ['weight_kg' => 74.0, 'systolic_bp' => 122, 'diastolic_bp' => 80, 'temperature_c' => 36.7, 'pulse_rate' => 76],
                    ],
                    [
                        'date' => '2026-10-07', 'reason' => 'Skin and hair concerns',
                        'notes' => 'Growing facial hair on her chin over the last few months.',
                        'diagnosis' => 'Under investigation',
                        'vitals' => ['weight_kg' => 76.5, 'systolic_bp' => 124, 'diastolic_bp' => 82, 'temperature_c' => 36.9, 'pulse_rate' => 78],
                    ],
                ],
            ],

            // Case 3: different features with an UNRELATED visit in between -> 2 of 3, open alert.
            [
                'patient' => ['first_name' => 'Chisomo', 'last_name' => 'Mwale', 'date_of_birth' => '1995-11-27', 'phone' => '+265 995 300 003', 'address' => 'Limbe, Blantyre'],
                'height_cm' => 165.0,
                'visits' => [
                    [
                        'date' => '2026-03-10', 'reason' => 'Skin problem',
                        'notes' => 'Pimples on her forehead and chin for a year.',
                        'diagnosis' => 'Acne vulgaris',
                        'vitals' => ['weight_kg' => 68.0, 'systolic_bp' => 118, 'diastolic_bp' => 76, 'temperature_c' => 36.6, 'pulse_rate' => 74],
                    ],
                    [
                        'date' => '2026-06-16', 'reason' => 'Fever',
                        'notes' => 'Fever and headache for 3 days. Joint pains.',
                        'diagnosis' => 'Malaria',
                        'vitals' => ['weight_kg' => 66.5, 'systolic_bp' => 112, 'diastolic_bp' => 70, 'temperature_c' => 38.9, 'pulse_rate' => 104],
                    ],
                    [
                        'date' => '2026-09-22', 'reason' => 'Ultrasound review',
                        'notes' => 'Ultrasound: polycystic ovaries on both sides.',
                        'diagnosis' => 'Under investigation',
                        'vitals' => ['weight_kg' => 68.5, 'systolic_bp' => 120, 'diastolic_bp' => 78, 'temperature_c' => 36.7, 'pulse_rate' => 72],
                    ],
                ],
            ],

            // Case 4: alert -> referred -> RULED OUT -> new symptom later -> new alert mentioning the earlier result.
            [
                'patient' => ['first_name' => 'Thandiwe', 'last_name' => 'Chirwa', 'date_of_birth' => '1999-06-09', 'phone' => '+265 884 400 004', 'address' => 'Soche, Blantyre'],
                'height_cm' => 160.0,
                'visits' => [
                    [
                        'date' => '2026-02-10', 'reason' => 'Period problems',
                        'notes' => 'Periods come every 3 months. Pimples on her face.',
                        'diagnosis' => 'Possible PMOS',
                        'vitals' => ['weight_kg' => 70.0, 'systolic_bp' => 118, 'diastolic_bp' => 78, 'temperature_c' => 36.6, 'pulse_rate' => 74],
                        'referred_on' => '2026-02-10',
                        'result' => ['ruled_out', '2026-04-21'],
                    ],
                    [
                        'date' => '2026-09-29', 'reason' => 'Pelvic pain',
                        'notes' => 'Ultrasound shows polycystic ovaries.',
                        'diagnosis' => 'Under investigation',
                        'vitals' => ['weight_kg' => 71.5, 'systolic_bp' => 120, 'diastolic_bp' => 80, 'temperature_c' => 36.8, 'pulse_rate' => 76],
                    ],
                ],
            ],

            // Case 5: alert -> referred -> PMOS CONFIRMED -> later symptoms raise no alert.
            [
                'patient' => ['first_name' => 'Mphatso', 'last_name' => 'Kamanga', 'date_of_birth' => '1996-01-30', 'phone' => '+265 999 500 005', 'address' => 'Chilomoni, Blantyre'],
                'height_cm' => 163.0,
                'visits' => [
                    [
                        'date' => '2026-01-13', 'reason' => 'Missed periods',
                        'notes' => 'No period for 5 months and facial hair.',
                        'diagnosis' => 'Possible PMOS',
                        'vitals' => ['weight_kg' => 82.0, 'systolic_bp' => 126, 'diastolic_bp' => 84, 'temperature_c' => 36.7, 'pulse_rate' => 80],
                        'referred_on' => '2026-01-13',
                        'result' => ['confirmed', '2026-03-02'],
                    ],
                    [
                        'date' => '2026-08-18', 'reason' => 'Skin problem',
                        'notes' => 'Acne on her back.',
                        'diagnosis' => 'Acne, PMOS-related',
                        'vitals' => ['weight_kg' => 80.5, 'systolic_bp' => 124, 'diastolic_bp' => 82, 'temperature_c' => 36.6, 'pulse_rate' => 78],
                    ],
                ],
            ],

            // Case 6: alert -> referred -> AWAITING RESULTS.
            [
                'patient' => ['first_name' => 'Lusungu', 'last_name' => 'Nyirenda', 'date_of_birth' => '2001-12-05', 'phone' => '+265 886 600 006', 'address' => 'Bangwe, Blantyre'],
                'height_cm' => 167.0,
                'visits' => [
                    [
                        'date' => '2026-10-08', 'reason' => 'Irregular periods',
                        'notes' => 'Irregular periods since menarche. Hair thinning at the crown.',
                        'diagnosis' => 'Possible PMOS',
                        'vitals' => ['weight_kg' => 77.0, 'systolic_bp' => 122, 'diastolic_bp' => 80, 'temperature_c' => 36.8, 'pulse_rate' => 82],
                        'referred_on' => '2026-10-08',
                    ],
                ],
            ],
        ];
    }
}
