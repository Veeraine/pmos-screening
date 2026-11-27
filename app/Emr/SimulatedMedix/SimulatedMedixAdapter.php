<?php

namespace App\Emr\SimulatedMedix;

use App\Emr\ConsultationNotes;
use App\Emr\EmrAdapter;
use App\Emr\EmrPatient;
use App\Emr\EmrVisit;
use App\Emr\EmrVitalSigns;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\VitalSign;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Stand-in for Chitawira's EMR (Medix) until the real one can be connected. Uses own tables as Medix's database.
 */
final class SimulatedMedixAdapter implements EmrAdapter
{
    public function __construct(private Hasher $hasher) {}

    public function systemName(): string
    {
        return 'Medix (simulated)';
    }

    public function findPatient(string $patientId): ?EmrPatient
    {
        $patient = Patient::with('registeredBy:id,name')->find($patientId);

        return $patient ? $this->toEmrPatient($patient) : null;
    }

    public function findPatients(array $patientIds): Collection
    {
        if ($patientIds === []) {
            return collect();
        }

        return Patient::with('registeredBy:id,name')
            ->whereKey(array_unique($patientIds))
            ->get()
            ->mapWithKeys(fn (Patient $patient): array => [(string) $patient->id => $this->toEmrPatient($patient)]);
    }

    public function searchPatients(string $search, int $perPage): LengthAwarePaginator
    {
        $patients = Patient::query()
            ->with('registeredBy:id,name')
            ->when($search !== '', fn ($query) => $query->search($search)->orderBy('last_name')->orderBy('first_name'))
            ->when($search === '', fn ($query) => $query->latest('id'))
            ->paginate($perPage);

        $patients->setCollection($patients->getCollection()->map(fn (Patient $patient): EmrPatient => $this->toEmrPatient($patient)));

        return $patients;
    }

    public function findVisit(string $visitId): ?EmrVisit
    {
        $visit = $this->visitQuery()->find($visitId);

        return $visit ? $this->toEmrVisit($visit) : null;
    }

    public function visitsForPatient(string $patientId): Collection
    {
        return $this->visitQuery()
            ->where('patient_id', $patientId)
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Visit $visit): EmrVisit => $this->toEmrVisit($visit));
    }

    public function latestVitalSigns(array $patientIds): Collection
    {
        $latest = collect();

        // Latest vital signs for each patient, 500 patients per query.
        foreach (array_chunk(array_values(array_unique($patientIds)), 500) as $chunk) {
            VitalSign::query()
                ->join('visits', 'visits.id', '=', 'vital_signs.visit_id')
                ->whereIn('visits.patient_id', $chunk)
                ->where('vital_signs.id', function ($query): void {
                    $query->select('newest.id')
                        ->from('vital_signs as newest')
                        ->join('visits as newest_visit', 'newest_visit.id', '=', 'newest.visit_id')
                        ->whereColumn('newest_visit.patient_id', 'visits.patient_id')
                        ->orderByDesc('newest_visit.visit_date')
                        ->orderByDesc('newest.id')
                        ->limit(1);
                })
                ->select('vital_signs.*', 'visits.patient_id as visit_patient_id')
                ->get()
                ->each(function (VitalSign $vitalSigns) use ($latest): void {
                    $latest->put((string) $vitalSigns->visit_patient_id, $this->toEmrVitalSigns($vitalSigns));
                });
        }

        return $latest;
    }

    public function todaysVisits(): Collection
    {
        return $this->visitQuery()
            ->today()
            ->oldest('id')
            ->get()
            ->map(fn (Visit $visit): EmrVisit => $this->toEmrVisit($visit));
    }

    public function saveConsultationNotes(string $visitId, ConsultationNotes $notes, string $doctorId): void
    {
        $visit = Visit::findOrFail($visitId);
        $visit->fill([
            'notes' => $notes->notes,
            'menstrual_history' => $notes->menstrualHistory,
            'diagnosis' => $notes->diagnosis,
        ]);
        $visit->consulted_by = $doctorId;
        $visit->consulted_at = now();
        $visit->save();
    }

    public function staffNames(array $staffIds): array
    {
        if ($staffIds === []) {
            return [];
        }

        return User::whereKey(array_unique($staffIds))
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [(string) $id => $name])
            ->all();
    }

    public function loginProvider(): UserProvider
    {
        return new EloquentUserProvider($this->hasher, User::class);
    }

    /**
     * Visits with everything an EmrVisit needs, without extra queries per visit.
     *
     * @return Builder<Visit>
     */
    private function visitQuery(): Builder
    {
        return Visit::with(['checkedInBy:id,name', 'consultedBy:id,name', 'vitalSigns']);
    }

    private function toEmrPatient(Patient $patient): EmrPatient
    {
        return new EmrPatient(
            id: (string) $patient->id,
            patientNumber: $patient->patient_number,
            firstName: $patient->first_name,
            lastName: $patient->last_name,
            dateOfBirth: CarbonImmutable::parse($patient->date_of_birth),
            phone: $patient->phone,
            address: $patient->address,
            previousDiagnoses: $patient->previous_diagnoses,
            registeredAt: CarbonImmutable::parse($patient->created_at),
            registeredByName: $patient->registeredBy?->name,
        );
    }

    private function toEmrVisit(Visit $visit): EmrVisit
    {
        return new EmrVisit(
            id: (string) $visit->id,
            patientId: (string) $visit->patient_id,
            date: CarbonImmutable::parse($visit->visit_date),
            checkedInAt: CarbonImmutable::parse($visit->created_at),
            reason: $visit->reason,
            checkedInByName: $visit->checkedInBy?->name,
            vitalSigns: $visit->vitalSigns ? $this->toEmrVitalSigns($visit->vitalSigns) : null,
            notes: $visit->notes,
            menstrualHistory: $visit->menstrual_history,
            diagnosis: $visit->diagnosis,
            consultedAt: $visit->consulted_at ? CarbonImmutable::parse($visit->consulted_at) : null,
            consultedByName: $visit->consultedBy?->name,
        );
    }

    private function toEmrVitalSigns(VitalSign $vitalSigns): EmrVitalSigns
    {
        return new EmrVitalSigns(
            weightKg: $vitalSigns->weight_kg,
            heightCm: $vitalSigns->height_cm,
            bmi: $vitalSigns->bmi,
            systolicBp: $vitalSigns->systolic_bp,
            diastolicBp: $vitalSigns->diastolic_bp,
            temperatureC: $vitalSigns->temperature_c,
            pulseRate: $vitalSigns->pulse_rate,
        );
    }
}
