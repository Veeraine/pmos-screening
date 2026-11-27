<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A symptom the doctor confirmed at a visit (patient and visit are EMR IDs).
 */
class ConfirmedSymptom extends Model
{
    public const SOURCE_DETECTED = 'detected';

    public const SOURCE_MANUAL = 'manual';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emr_patient_id' => 'string',
            'emr_visit_id' => 'string',
            'confirmed_by' => 'string',
        ];
    }

    /**
     * @return BelongsTo<Symptom, $this>
     */
    public function symptom(): BelongsTo
    {
        return $this->belongsTo(Symptom::class);
    }

    // Symptoms confirmed for the given EMR patient.

    #[Scope]
    protected function forPatient(Builder $query, string $emrPatientId): Builder
    {
        return $query->where('emr_patient_id', $emrPatientId);
    }

   // Symptoms confirmed at the given EMR visit.
   
    #[Scope]
    protected function forVisit(Builder $query, string $emrVisitId): Builder
    {
        return $query->where('emr_visit_id', $emrVisitId);
    }
}
