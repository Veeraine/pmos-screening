<?php

namespace App\Emr;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * A patient's details from the EMR it is read only
 */
final readonly class EmrPatient
{
    public function __construct(
        public string $id,
        public string $patientNumber,
        public string $firstName,
        public string $lastName,
        public CarbonImmutable $dateOfBirth,
        public ?string $phone,
        public ?string $address,
        public ?string $previousDiagnoses,
        public CarbonImmutable $registeredAt,
        public ?string $registeredByName,
    ) {}

    public function fullName(): string
    {
        return "{$this->firstName} {$this->lastName}";
    }

    // The patient's age in whole years.
     
    public function age(): int
    {
        return $this->ageOn(now());
    }

    //The patient's age in whole years on the given date.
     
    public function ageOn(CarbonInterface $date): int
    {
        return (int) $this->dateOfBirth->startOfDay()->diffInYears($date->copy()->startOfDay());
    }
}
