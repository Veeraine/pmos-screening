<?php

namespace App\Emr;

use App\Role;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A logged-in staff member from the EMR 
 */
interface StaffAccount extends Authenticatable
{
    //Staff ID in the EMR, stored on screening records to show who did what.

    public function staffId(): string;

    //The staff member's name, e.g. "Dr Chisomo Banda".

    public function staffName(): string;

    public function staffRole(): Role;

    public function hasRole(Role ...$roles): bool;

    public function isActiveStaff(): bool;
}
