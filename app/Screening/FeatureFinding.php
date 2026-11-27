<?php

namespace App\Screening;

use App\Emr\EmrVisit;
use App\Models\ConfirmedSymptom;

/**
 * One Rotterdam feature and the confirmed symptoms (with their visits) that show it.
 */
final readonly class FeatureFinding
{
    /**
     * @param  string  $key  The feature key from config/screening.php, e.g. "high_androgens".
     * @param  string  $label  The name doctors see, e.g. "Signs of high androgen levels".
     * @param  list<array{visit: EmrVisit, confirmed: ConfirmedSymptom}>  $evidence  Newest visit first.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $evidence,
    ) {}

    // Whether any confirmed symptom shows this feature.
    public function isFound(): bool
    {
        return $this->evidence !== [];
    }
}
