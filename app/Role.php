<?php

namespace App;

/**
 * The staff roles in the system. Each user has exactly one role.
 */
enum Role: string
{
    case Admin = 'admin';
    case Nurse = 'nurse';
    case Doctor = 'doctor';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
