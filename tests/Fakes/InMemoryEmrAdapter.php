<?php

namespace Tests\Fakes;

use App\Emr\ConsultationNotes;
use App\Emr\EmrAdapter;
use App\Emr\EmrPatient;
use App\Emr\EmrVisit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * A pretend EMR kept in memory, with different style IDs (e.g. "MDX-P-501").
 * Used to show the screening works with any EMR, not just the simulated Medix.
 */
final class InMemoryEmrAdapter implements EmrAdapter
{
    /** @var array<string, EmrPatient> */
    public array $patients = [];

    /** @var array<string, EmrVisit> */
    public array $visits = [];

    /** @var array<string, FakeStaffAccount> */
    public array $staff = [];

    public function addPatient(EmrPatient $patient): void
    {
        $this->patients[$patient->id] = $patient;
    }

    public function addVisit(EmrVisit $visit): void
    {
        $this->visits[$visit->id] = $visit;
    }

    public function addStaff(FakeStaffAccount $staff): void
    {
        $this->staff[$staff->staffId()] = $staff;
    }

    public function systemName(): string
    {
        return 'Test EMR';
    }

    public function findPatient(string $patientId): ?EmrPatient
    {
        return $this->patients[$patientId] ?? null;
    }

    public function findPatients(array $patientIds): Collection
    {
        return collect($this->patients)->only($patientIds);
    }

    public function searchPatients(string $search, int $perPage): LengthAwarePaginator
    {
        $matches = collect($this->patients)
            ->filter(fn (EmrPatient $patient): bool => $search === '' || str_contains(strtolower($patient->fullName()), strtolower($search)))
            ->values();

        return new Paginator($matches->take($perPage), $matches->count(), $perPage);
    }

    public function findVisit(string $visitId): ?EmrVisit
    {
        return $this->visits[$visitId] ?? null;
    }

    public function visitsForPatient(string $patientId): Collection
    {
        return collect($this->visits)
            ->filter(fn (EmrVisit $visit): bool => $visit->patientId === $patientId)
            ->sortByDesc(fn (EmrVisit $visit): string => $visit->date->toDateString())
            ->values();
    }

    public function latestVitalSigns(array $patientIds): Collection
    {
        return collect($this->visits)
            ->filter(fn (EmrVisit $visit): bool => $visit->vitalSigns !== null && in_array($visit->patientId, $patientIds, true))
            ->sortByDesc(fn (EmrVisit $visit): string => $visit->date->toDateString())
            ->unique('patientId')
            ->mapWithKeys(fn (EmrVisit $visit): array => [$visit->patientId => $visit->vitalSigns]);
    }

    public function todaysVisits(): Collection
    {
        return collect($this->visits)->filter(fn (EmrVisit $visit): bool => $visit->isToday())->values();
    }

    public function saveConsultationNotes(string $visitId, ConsultationNotes $notes, string $doctorId): void
    {
        $visit = $this->visits[$visitId];

        $this->visits[$visitId] = new EmrVisit(
            id: $visit->id,
            patientId: $visit->patientId,
            date: $visit->date,
            checkedInAt: $visit->checkedInAt,
            reason: $visit->reason,
            checkedInByName: $visit->checkedInByName,
            vitalSigns: $visit->vitalSigns,
            notes: $notes->notes,
            menstrualHistory: $notes->menstrualHistory,
            diagnosis: $notes->diagnosis,
            consultedAt: CarbonImmutable::now(),
            consultedByName: $this->staff[$doctorId]->staffName(),
        );
    }

    public function staffNames(array $staffIds): array
    {
        return collect($this->staff)
            ->only($staffIds)
            ->map(fn (FakeStaffAccount $staff): string => $staff->staffName())
            ->all();
    }

    public function loginProvider(): UserProvider
    {
        $staff = &$this->staff;

        return new class($staff) implements UserProvider
        {
            /** @param  array<string, FakeStaffAccount>  $staff */
            public function __construct(private array &$staff) {}

            public function retrieveById($identifier): ?Authenticatable
            {
                return $this->staff[$identifier] ?? null;
            }

            public function retrieveByToken($identifier, $token): ?Authenticatable
            {
                return null;
            }

            public function updateRememberToken(Authenticatable $user, $token): void {}

            public function retrieveByCredentials(array $credentials): ?Authenticatable
            {
                return null;
            }

            public function validateCredentials(Authenticatable $user, array $credentials): bool
            {
                return false;
            }

            public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
        };
    }
}
