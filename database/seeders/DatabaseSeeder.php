<?php

namespace Database\Seeders;

use App\Models\User;
use App\Role;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * One test account per role plus sample patients.
     * All passwords are "password", so change them before real use.
     */
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            User::firstOrCreate(
                ['email' => $role->value.'@chitawira.test'],
                [
                    'name' => 'Test '.$role->label(),
                    'role' => $role,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->call([
            PatientSeeder::class,
            ScreeningDemoSeeder::class,
        ]);
    }
}
