<?php

namespace App\Screening;

/**
 * Result of a screening check: each Rotterdam sign with its evidence, and if there's enough for an alert.
 */
final readonly class ScreeningResult
{
    /**
     * @param  list<FeatureFinding>  $features  Every feature, in the order of the rules file.
     */
    public function __construct(
        public array $features,
        public int $threshold,
    ) {}

    /**
     * The features that were found.
     *
     * @return list<FeatureFinding>
     */
    public function foundFeatures(): array
    {
        return array_values(array_filter($this->features, fn (FeatureFinding $feature): bool => $feature->isFound()));
    }

    /**
     * How many features were found.
     */
    public function foundCount(): int
    {
        return count($this->foundFeatures());
    }

    // Whether enough features were found to raise an alert.
    public function meetsThreshold(): bool
    {
        return $this->foundCount() >= $this->threshold;
    }
}
