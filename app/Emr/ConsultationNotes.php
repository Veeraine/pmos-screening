<?php

namespace App\Emr;


 //What the doctor writes at a consultation, to be saved in the EMR.

final readonly class ConsultationNotes
{
    public function __construct(
        public ?string $notes,
        public ?string $menstrualHistory,
        public ?string $diagnosis,
    ) {}
}
