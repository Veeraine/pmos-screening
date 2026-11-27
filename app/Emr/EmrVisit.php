<?php

namespace App\Emr;

use Carbon\CarbonImmutable;

/**
 * One visit from the EMR with its vital signs and doctor's notes it is read only.
 */
final readonly class EmrVisit
{
    public function __construct(
        public string $id,
        public string $patientId,
        public CarbonImmutable $date,
        public CarbonImmutable $checkedInAt,
        public string $reason,
        public ?string $checkedInByName,
        public ?EmrVitalSigns $vitalSigns,
        public ?string $notes,
        public ?string $menstrualHistory,
        public ?string $diagnosis,
        public ?CarbonImmutable $consultedAt,
        public ?string $consultedByName,
    ) {}

    // Whether a doctor has saved a consultation for this visit.
     
    public function isConsulted(): bool
    {
        return $this->consultedAt !== null;
    }

    //Whether the visit is today.
    
    public function isToday(): bool
    {
        return $this->date->isToday();
    }
}
