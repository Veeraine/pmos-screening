<?php

namespace App;

/**
 * The result of a referral for PMOS screening, recorded by the doctor.
 */
enum ScreeningOutcome: string
{
    case Confirmed = 'confirmed';
    case RuledOut = 'ruled_out';

    // The wording shown on screen, e.g. "PMOS confirmed".
    public function label(): string
    {
        return match ($this) {
            self::Confirmed => __('PMOS confirmed'),
            self::RuledOut => __('PMOS ruled out'),
        };
    }
}
