<?php

namespace App\Models;

use Database\Factories\VitalSignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Vital signs in the simulated Medix (see App\Emr\SimulatedMedix).
 */
#[Fillable(['weight_kg', 'height_cm', 'systolic_bp', 'diastolic_bp', 'temperature_c', 'pulse_rate'])]
class VitalSign extends Model
{
    /** @use HasFactory<VitalSignFactory> */
    use HasFactory;

    
    // Works out BMI on save, using this visit's height or the last recorded one.
    
    protected static function booted(): void
    {
        static::saving(function (VitalSign $vitalSign): void {
            $heightCm = $vitalSign->height_cm ?? $vitalSign->visit->lastRecordedHeight()?->height_cm;

            if ($heightCm === null) {
                throw new LogicException('BMI cannot be calculated: this patient has no recorded height.');
            }

            $heightInMetres = $heightCm / 100;

            $vitalSign->bmi = round($vitalSign->weight_kg / ($heightInMetres ** 2), 1);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:1',
            'height_cm' => 'decimal:1',
            'bmi' => 'decimal:1',
            'systolic_bp' => 'integer',
            'diastolic_bp' => 'integer',
            'temperature_c' => 'decimal:1',
            'pulse_rate' => 'integer',
        ];
    }

    /**
     * The visit these vital signs were recorded at.
     *
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * The nurse who recorded these vital signs.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Blood pressure written the usual way, e.g. "120/80".
    
    protected function bloodPressure(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->systolic_bp}/{$this->diastolic_bp}");
    }
}
