<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A patient in the simulated Medix EMR. The screening part only gets patients through the EMR adapter.
 */
#[Fillable(['first_name', 'last_name', 'date_of_birth', 'phone', 'address', 'previous_diagnoses'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    // Every patient number starts with this, e.g. "CPH-000042".
    public const NUMBER_PREFIX = 'CPH-';

    //  Gives each new patient a number based on their ID.

    protected static function booted(): void
    {
        static::created(function (Patient $patient): void {
            $patient->patient_number = self::NUMBER_PREFIX.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT);
            $patient->saveQuietly();
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
            'date_of_birth' => 'date',
        ];
    }

    /**
     * The nurse who registered this patient.
     *
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * All of this patient's visits.
     *
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * First name and surname together.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->first_name} {$this->last_name}");
    }

    /**
     * The patient's age in whole years.
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->date_of_birth->diffInYears(now()));
    }

    /**
     * Search by patient number, name or phone. Every word has to match, so "grace phiri" doesn't find every Grace.
     */
    #[Scope]
    protected function search(Builder $query, string $term): Builder
    {
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word);

            $query->where(function (Builder $query) use ($escaped): void {
                $query->whereLike('patient_number', "%{$escaped}%")
                    ->orWhereLike('first_name', "{$escaped}%")
                    ->orWhereLike('last_name', "{$escaped}%")
                    ->orWhereLike('phone', "{$escaped}%");
            });
        }

        return $query;
    }

    /**
     * Find existing patients with the same name and date of birth.
     *
     * @param  array{first_name: string, last_name: string, date_of_birth: string}  $details
     */
    #[Scope]
    protected function possibleDuplicatesOf(Builder $query, array $details): Builder
    {
        return $query
            ->where('last_name', $details['last_name'])
            ->where('first_name', $details['first_name'])
            ->whereDate('date_of_birth', $details['date_of_birth']);
    }
}
