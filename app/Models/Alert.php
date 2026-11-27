<?php

namespace App\Models;

use App\AlertStatus;
use App\ScreeningOutcome;
use Carbon\CarbonInterface;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A screening alert. Patient, visit and staff are in the EMR so only their IDs are stored.
 * "findings" is a copy of the Rotterdam signs and evidence at the time the alert was raised.
 */
#[Fillable(['findings'])]
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emr_patient_id' => 'string',
            'raised_at_emr_visit_id' => 'string',
            'decided_by' => 'string',
            'outcome_recorded_by' => 'string',
            'status' => AlertStatus::class,
            'findings' => 'array',
            'referred' => 'boolean',
            'decided_at' => 'datetime',
            'outcome' => ScreeningOutcome::class,
            'outcome_date' => 'date',
            'outcome_recorded_at' => 'datetime',
        ];
    }

    /**
     * The patient's alert before this one, whose result this alert mentions.
     *
     * @return BelongsTo<Alert, $this>
     */
    public function previousAlert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'previous_alert_id');
    }

    /**
     * Every correction made to this alert's decision or result, oldest first.
     *
     * @return HasMany<AlertCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(AlertCorrection::class)->oldest('id');
    }

    
    // Only the latest alert can be corrected, because later alerts depend on earlier results.
     
    public function isLatestForPatient(): bool
    {
        return ! Alert::query()->forPatient($this->emr_patient_id)->where('id', '>', $this->id)->exists();
    }

    // Whether the doctor still needs to decide about this alert.
    
    public function isOpen(): bool
    {
        return $this->status === AlertStatus::Open;
    }

    // Whether the patient was referred and the result is not back yet.
    
    public function isAwaitingResults(): bool
    {
        return $this->status === AlertStatus::Closed && $this->referred === true && $this->outcome === null;
    }

    /**
     * A new alert needs a symptom confirmed after this date (ruled out date or not-referred date).
     * Null if no new alert is allowed.
     */
    public function newSymptomsNeededAfter(): ?CarbonInterface
    {
        if ($this->outcome === ScreeningOutcome::RuledOut) {
            return $this->outcome_date;
        }

        if ($this->status === AlertStatus::Closed && $this->referred === false) {
            return $this->decided_at->copy()->startOfDay();
        }

        return null;
    }

   // How many Rotterdam features this alert found.

    public function featureCount(): int
    {
        return count($this->findings);
    }

    /**
     * IDs of all staff involved with this alert, so their names can be loaded from the EMR in one go.
     *
     * @return list<string>
     */
    public function staffIds(): array
    {
        return collect([$this->decided_by, $this->outcome_recorded_by])
            ->merge($this->corrections->pluck('corrected_by'))
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

   //Alerts for the given EMR patient.
   
    #[Scope]
    protected function forPatient(Builder $query, string $emrPatientId): Builder
    {
        return $query->where('emr_patient_id', $emrPatientId);
    }

    // Alerts waiting for the doctor's decision.
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::Open);
    }

    // Referred alerts whose outcome has not been recorded yet.
    
    #[Scope]
    protected function awaitingResults(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::Closed)->where('referred', true)->whereNull('outcome');
    }
}
