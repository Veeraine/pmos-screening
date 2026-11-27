<?php

namespace Tests;

use App\Models\Alert;
use App\Models\ConfirmedSymptom;
use App\Models\Patient;
use App\Models\Symptom;
use App\Models\Visit;
use App\Screening\ScreeningCheck;
use App\Screening\ScreeningResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Saves symptoms as if the doctor confirmed them at a visit.
     * Pass a code, or code => details to set the source or matched text.
     *
     * @param  array<int|string, string|array{source?: string, matched_text?: ?string}>  $symptoms
     */
    protected function confirmSymptoms(Visit $visit, array $symptoms): void
    {
        foreach ($symptoms as $key => $value) {
            [$code, $details] = is_int($key) ? [$value, []] : [$key, $value];
            $source = $details['source'] ?? ConfirmedSymptom::SOURCE_DETECTED;
            $defaultMatchedText = $source === ConfirmedSymptom::SOURCE_DETECTED ? "notes mentioning {$code}" : null;

            $confirmed = new ConfirmedSymptom;
            $confirmed->forceFill([
                'emr_patient_id' => (string) $visit->patient_id,
                'emr_visit_id' => (string) $visit->id,
                'symptom_id' => Symptom::where('code', $code)->value('id'),
                'source' => $source,
                'matched_text' => array_key_exists('matched_text', $details) ? $details['matched_text'] : $defaultMatchedText,
                'confirmed_by' => $visit->consulted_by === null ? null : (string) $visit->consulted_by,
            ])->save();
        }
    }

    /**
     * Gets the symptoms confirmed at a visit, in tick list order.
     *
     * @return Collection<int, ConfirmedSymptom>
     */
    protected function confirmedSymptomsAt(Visit $visit): Collection
    {
        return ConfirmedSymptom::forVisit((string) $visit->id)->with('symptom')->get()->sortBy('symptom.sort_order')->values();
    }

    /**
     * Get a patient's screening alerts, so a test can count or check them.
     *
     * @return Builder<Alert>
     */
    protected function alertsFor(Patient $patient): Builder
    {
        return Alert::forPatient((string) $patient->id);
    }

    /**
     * Runs the screening check for a patient.
     */
    protected function screeningCheck(Patient $patient): ScreeningResult
    {
        return app(ScreeningCheck::class)->run((string) $patient->id);
    }
}
