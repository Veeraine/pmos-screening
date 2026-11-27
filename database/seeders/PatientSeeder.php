<?php

namespace Database\Seeders;

use App\Models\Patient;
use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Add sample patients, registered by the development nurse account.
     */
    public function run(): void
    {
        if (Patient::exists()) {
            return;
        }

        $nurse = User::where('role', Role::Nurse)->firstOrFail();

        Patient::factory()
            ->count(15)
            ->create(['registered_by' => $nurse->id]);
    }
}
