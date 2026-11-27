<?php

namespace App\Emr;

/**
 * Vital signs from the EMR for one visit it is read only, numbers kept as the EMR shows them
 */
final readonly class EmrVitalSigns
{
    public function __construct(
        public string $weightKg,
        public ?string $heightCm,
        public string $bmi,
        public int $systolicBp,
        public int $diastolicBp,
        public string $temperatureC,
        public int $pulseRate,
    ) {}

    public function bloodPressure(): string
    {
        return "{$this->systolicBp}/{$this->diastolicBp}";
    }
}
