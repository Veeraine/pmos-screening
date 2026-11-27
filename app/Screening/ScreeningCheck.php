<?php

namespace App\Screening;

use App\Emr\EmrAdapter;
use App\Emr\EmrVisit;
use App\Models\ConfirmedSymptom;
use Illuminate\Support\Collection;

/**
 * The screening check it groups confirmed symptoms from all visits into the Rotterdam signs
 * in config/screening.php and compares them to the alert threshold.
 */
final class ScreeningCheck
{
    public function __construct(private EmrAdapter $emr) {}

    /**
     * Runs the check for a patient. Pass the visits if you already have them. 
     *
     * @param  Collection<int, EmrVisit>|null  $visits
     */
    public function run(string $emrPatientId, ?Collection $visits = null): ScreeningResult
    {
        $visitsById = ($visits ?? $this->emr->visitsForPatient($emrPatientId))->keyBy('id');

        $confirmedNewestFirst = ConfirmedSymptom::forPatient($emrPatientId)
            ->with('symptom')
            ->get()
            ->filter(fn (ConfirmedSymptom $confirmed): bool => $visitsById->has($confirmed->emr_visit_id))
            ->sort(fn (ConfirmedSymptom $a, ConfirmedSymptom $b): int => $visitsById[$b->emr_visit_id]->date <=> $visitsById[$a->emr_visit_id]->date
                ?: strnatcmp($b->emr_visit_id, $a->emr_visit_id)
                ?: $a->symptom->sort_order <=> $b->symptom->sort_order);

        $features = [];

        foreach (config('screening.rotterdam_features') as $key => $feature) {
            $evidence = [];

            foreach ($confirmedNewestFirst as $confirmed) {
                if (in_array($confirmed->symptom->code, $feature['symptoms'], true)) {
                    $evidence[] = ['visit' => $visitsById[$confirmed->emr_visit_id], 'confirmed' => $confirmed];
                }
            }

            $features[] = new FeatureFinding($key, $feature['label'], $evidence);
        }

        return new ScreeningResult($features, (int) config('screening.alert_threshold'));
    }
}
