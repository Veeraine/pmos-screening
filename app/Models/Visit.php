<?php

namespace App\Models;

use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A visit in the simulated Medix EMR. The screening part gets visits through the EMR adapter.
 */
#[Fillable(['visit_date', 'reason', 'menstrual_history', 'notes', 'diagnosis'])]
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'consulted_at' => 'datetime',
        ];
    }

    /**
     * The patient this visit belongs to.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The nurse who checked the patient in.
     *
     * @return BelongsTo<User, $this>
     */
    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * The vital signs the nurse recorded at this visit, if any yet.
     *
     * @return HasOne<VitalSign, $this>
     */
    public function vitalSigns(): HasOne
    {
        return $this->hasOne(VitalSign::class);
    }

    /**
     * The doctor who saw the patient at this visit, once the consultation is saved.
     *
     * @return BelongsTo<User, $this>
     */
    public function consultedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consulted_by');
    }

    // Whether a doctor has saved a consultation for this visit.
    public function isConsulted(): bool
    {
        return $this->consulted_at !== null;
    }

    public function lastRecordedHeight(): ?VitalSign
    {
        return VitalSign::query()
            ->select('vital_signs.*')
            ->join('visits', 'visits.id', '=', 'vital_signs.visit_id')
            ->where('visits.patient_id', $this->patient_id)
            ->where('visits.id', '!=', $this->id)
            ->whereNotNull('vital_signs.height_cm')
            ->orderByDesc('visits.visit_date')
            ->with('visit:id,visit_date')
            ->first();
    }

    // Only visits that happened today.
    
    #[Scope]
    protected function today(Builder $query): Builder
    {
        return $query->whereDate('visit_date', today());
    }
}
