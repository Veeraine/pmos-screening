<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A valid "Register patient" form submission.
     *
     * @return array<string, string>
     */
    private function validPatientForm(array $overrides = []): array
    {
        return [
            'first_name' => 'Chikondi',
            'last_name' => 'Banda',
            'date_of_birth' => '1998-04-17',
            'phone' => '+265 991 234 567',
            'address' => 'Chitawira, Blantyre',
            'previous_diagnoses' => 'Malaria (2023)',
            ...$overrides,
        ];
    }

    private function nurse(): User
    {
        return User::factory()->role(Role::Nurse)->create();
    }

    public function test_guests_are_redirected_to_login_from_the_register_form(): void
    {
        $this->get('/patients/create')->assertRedirect('/login');
    }

    public function test_doctors_cannot_open_the_register_form(): void
    {
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->actingAs($doctor)->get('/patients/create')->assertForbidden();
    }

    public function test_admins_cannot_register_patients(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)
            ->post('/patients', $this->validPatientForm())
            ->assertForbidden();

        $this->assertDatabaseCount('patients', 0);
    }

    public function test_nurse_can_register_a_patient_who_gets_a_patient_number(): void
    {
        $nurse = $this->nurse();

        $response = $this->actingAs($nurse)->post('/patients', $this->validPatientForm());

        $patient = Patient::sole();

        $response->assertRedirect("/patients/{$patient->id}")
            ->assertSessionHas('status', "Chikondi Banda has been registered with patient number {$patient->patient_number}.");

        $this->assertMatchesRegularExpression('/^CPH-\d{6}$/', $patient->patient_number);
        $this->assertSame('Chikondi', $patient->first_name);
        $this->assertSame('Banda', $patient->last_name);
        $this->assertSame('1998-04-17', $patient->date_of_birth->toDateString());
        $this->assertSame('+265 991 234 567', $patient->phone);
        $this->assertSame('Malaria (2023)', $patient->previous_diagnoses);
        $this->assertTrue($patient->registeredBy->is($nurse));
    }

    public function test_patient_numbers_are_padded_to_six_digits(): void
    {
        $patient = Patient::factory()->create(['id' => 42]);

        $this->assertSame('CPH-000042', $patient->patient_number);
    }

    public function test_every_patient_gets_a_different_number(): void
    {
        $patients = Patient::factory()->count(3)->create();

        $this->assertCount(3, $patients->pluck('patient_number')->unique());
    }

    public function test_name_and_date_of_birth_are_required(): void
    {
        $this->actingAs($this->nurse())
            ->post('/patients', [])
            ->assertSessionHasErrors([
                'first_name' => 'The first name field is required.',
                'last_name' => 'The last name field is required.',
                'date_of_birth' => 'The date of birth field is required.',
            ]);
    }

    public function test_a_date_of_birth_in_the_future_is_rejected(): void
    {
        $this->actingAs($this->nurse())
            ->post('/patients', $this->validPatientForm(['date_of_birth' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors(['date_of_birth' => 'The date of birth cannot be in the future.']);
    }

    public function test_a_phone_number_with_letters_is_rejected(): void
    {
        $this->actingAs($this->nurse())
            ->post('/patients', $this->validPatientForm(['phone' => 'call me']))
            ->assertSessionHasErrors(['phone' => 'The phone number may only contain digits, spaces, + and -.']);
    }

    public function test_registering_the_same_name_and_date_of_birth_twice_is_stopped(): void
    {
        $existing = Patient::factory()->create([
            'first_name' => 'Chikondi',
            'last_name' => 'Banda',
            'date_of_birth' => '1998-04-17',
        ]);

        $this->actingAs($this->nurse())
            ->post('/patients', $this->validPatientForm())
            ->assertSessionHasErrors([
                'duplicate' => "A patient with this name and date of birth is already registered ({$existing->patient_number}). Open their record instead, or tick the box below if this is a different person.",
            ]);

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_a_different_person_with_the_same_name_and_date_of_birth_can_be_registered_after_confirming(): void
    {
        Patient::factory()->create([
            'first_name' => 'Chikondi',
            'last_name' => 'Banda',
            'date_of_birth' => '1998-04-17',
        ]);

        $this->actingAs($this->nurse())
            ->post('/patients', $this->validPatientForm(['confirmed_different_person' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('patients', 2);
    }

    public function test_the_same_name_with_a_different_date_of_birth_is_not_a_duplicate(): void
    {
        Patient::factory()->create([
            'first_name' => 'Chikondi',
            'last_name' => 'Banda',
            'date_of_birth' => '1990-01-01',
        ]);

        $this->actingAs($this->nurse())
            ->post('/patients', $this->validPatientForm())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('patients', 2);
    }

    public function test_nurses_and_doctors_can_view_a_patients_details(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Chikondi',
            'last_name' => 'Banda',
            'previous_diagnoses' => 'Malaria (2023)',
        ]);

        foreach ([Role::Nurse, Role::Doctor] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get("/patients/{$patient->id}")
                ->assertOk()
                ->assertSee('Chikondi Banda')
                ->assertSee($patient->patient_number)
                ->assertSee('Malaria (2023)');
        }
    }

    public function test_admins_cannot_view_patient_details(): void
    {
        $patient = Patient::factory()->create();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)->get("/patients/{$patient->id}")->assertForbidden();
    }
}
