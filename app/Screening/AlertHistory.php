<?php

namespace App\Screening;

use App\Models\Alert;
use App\ScreeningOutcome;
use Illuminate\Support\Collection;

/**
 * All of one patient's screening alerts, newest first.
 */
final readonly class AlertHistory
{
    /**
     * @param  Collection<int, Alert>  $alerts  Newest first.
     */
    public function __construct(public Collection $alerts) {}

    /**
     * Loads a patient's alerts, with any extra relations.
     *
     * @param  array<int|string, mixed>  $with
     */
    public static function forPatient(string $emrPatientId, array $with = []): self
    {
        return new self(Alert::forPatient($emrPatientId)->with($with)->latest('id')->get());
    }

    // The patient's most recent alert
    public function latest(): ?Alert
    {
        return $this->alerts->first();
    }

    // The alert where PMOS was confirmed. After that no more alerts are raised.
    public function pmosDiagnosis(): ?Alert
    {
        return $this->alerts->first(fn (Alert $alert): bool => $alert->outcome === ScreeningOutcome::Confirmed);
    }

    // One of the patient's alerts by ID, if it is in this history.
    public function find(?int $alertId): ?Alert
    {
        return $alertId === null ? null : $this->alerts->firstWhere('id', $alertId);
    }

    public function isEmpty(): bool
    {
        return $this->alerts->isEmpty();
    }

    /**
     * IDs of all staff involved with these alerts. Corrections need to be loaded first.
     *
     * @return list<string>
     */
    public function staffIds(): array
    {
        return $this->alerts->flatMap(fn (Alert $alert): array => $alert->staffIds())->unique()->values()->all();
    }
}
