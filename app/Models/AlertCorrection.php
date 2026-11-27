<?php

namespace App\Models;

use App\ScreeningOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One correction to an alert's decision or result. Old and new values are both kept so nothing is lost.
 */
class AlertCorrection extends Model
{
    public const FIELD_DECISION = 'decision';

    public const FIELD_OUTCOME = 'outcome';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'corrected_by' => 'string',
            'previous_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    // The value before the correction, in words.
    
    public function previousSummary(): string
    {
        return $this->summarise($this->previous_values);
    }

    public function newSummary(): string
    {
        return $this->summarise($this->new_values);
    }

    /**
     * Puts a decision or result in words, e.g. 'PMOS ruled out (21 Apr 2026)'.
     *
     * @param  array<string, mixed>  $values
     */
    private function summarise(array $values): string
    {
        if ($this->field === self::FIELD_DECISION) {
            return $values['referred']
                ? __('Referred')
                : __('Not referred (":reason")', ['reason' => $values['not_referred_reason']]);
        }

        return __(':outcome (:date)', [
            'outcome' => ScreeningOutcome::from($values['outcome'])->label(),
            'date' => Carbon::parse($values['outcome_date'])->format('d M Y'),
        ]);
    }
}
