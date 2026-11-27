<?php

namespace App\Screening;

use App\AlertStatus;
use App\Models\Alert;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Raises a screening alert after a visit if the patient now has 2 of the 3 Rotterdam signs.
 * No alert if one is already open, results are pending, PMOS is confirmed, or there's no new symptom since it was ruled out.
 */
final class AlertRaiser
{
    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(private ScreeningCheck $screeningCheck) {}

    // Runs the check after a visit is saved and returns the new alert (or null).
    
    public function raiseIfNeeded(string $emrPatientId, string $emrVisitId): ?Alert
    {
        return Cache::lock("pmos-screening-patient-{$emrPatientId}", 30)->block(
            self::LOCK_WAIT_SECONDS,
            fn (): ?Alert => DB::transaction(fn (): ?Alert => $this->raise($emrPatientId, $emrVisitId)),
        );
    }

    private function raise(string $emrPatientId, string $emrVisitId): ?Alert
    {
        $history = AlertHistory::forPatient($emrPatientId);
        $latestAlert = $history->latest();

        if ($history->pmosDiagnosis() || $latestAlert?->isOpen() || $latestAlert?->isAwaitingResults()) {
            return null;
        }

        $result = $this->screeningCheck->run($emrPatientId);

        if (! $result->meetsThreshold()) {
            return null;
        }

        $newSymptomsNeededAfter = $latestAlert?->newSymptomsNeededAfter();

        if ($newSymptomsNeededAfter && ! $this->hasEvidenceAfter($result, $newSymptomsNeededAfter)) {
            return null;
        }

        $alert = new Alert(['findings' => $this->findingsSnapshot($result, $newSymptomsNeededAfter)]);
        $alert->emr_patient_id = $emrPatientId;
        $alert->raised_at_emr_visit_id = $emrVisitId;
        $alert->previous_alert_id = $latestAlert?->id;
        $alert->status = AlertStatus::Open;
        $alert->save();

        return $alert;
    }

    /*Checks if a symptom that counts was confirmed at a visit after the date. */
    private function hasEvidenceAfter(ScreeningResult $result, CarbonInterface $date): bool
    {
        foreach ($result->foundFeatures() as $feature) {
            foreach ($feature->evidence as $item) {
                if ($item['visit']->date->gt($date)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Copy of what the check found, saved with the alert so it always shows why it was raised.
     * Symptoms after the earlier result are marked "new".
     *
     * @return list<array{key: string, label: string, evidence: list<array<string, mixed>>}>
     */
    private function findingsSnapshot(ScreeningResult $result, ?CarbonInterface $newSymptomsNeededAfter): array
    {
        return array_map(fn (FeatureFinding $feature): array => [
            'key' => $feature->key,
            'label' => $feature->label,
            'evidence' => array_map(fn (array $item): array => [
                'visit_id' => $item['visit']->id,
                'visit_date' => $item['visit']->date->toDateString(),
                'symptom' => $item['confirmed']->symptom->name,
                'matched_text' => $item['confirmed']->matched_text,
                'source' => $item['confirmed']->source,
                'is_new' => $newSymptomsNeededAfter !== null && $item['visit']->date->gt($newSymptomsNeededAfter),
            ], $feature->evidence),
        ], $result->foundFeatures());
    }
}
