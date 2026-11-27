<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientSearchTest extends TestCase
{
    use RefreshDatabase;

    private function nurse(): User
    {
        return User::factory()->role(Role::Nurse)->create();
    }

    public function test_admins_cannot_search_patients(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)->get('/patients')->assertForbidden();
    }

    public function test_a_patient_can_be_found_by_patient_number(): void
    {
        $wanted = Patient::factory()->create(['id' => 42, 'first_name' => 'Chikondi']);
        Patient::factory()->create(['id' => 7, 'first_name' => 'Tamara']);

        $this->actingAs($this->nurse())
            ->get('/patients?search=CPH-000042')
            ->assertOk()
            ->assertSee($wanted->full_name)
            ->assertDontSee('Tamara');
    }

    public function test_a_patient_can_be_found_by_the_start_of_their_name(): void
    {
        Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda']);
        Patient::factory()->create(['first_name' => 'Tamara', 'last_name' => 'Mwale']);

        $this->actingAs($this->nurse())
            ->get('/patients?search=chik')
            ->assertSee('Chikondi Banda')
            ->assertDontSee('Tamara Mwale');
    }

    public function test_every_word_of_a_full_name_search_must_match(): void
    {
        Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda']);
        Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Phiri']);

        $this->actingAs($this->nurse())
            ->get('/patients?search=Chikondi+Phiri')
            ->assertSee('Chikondi Phiri')
            ->assertDontSee('Chikondi Banda');
    }

    public function test_a_patient_can_be_found_by_phone_number(): void
    {
        Patient::factory()->create(['first_name' => 'Chikondi', 'phone' => '0991234567']);
        Patient::factory()->create(['first_name' => 'Tamara', 'phone' => '0888765432']);

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/patients?search=0991')
            ->assertSee('Chikondi')
            ->assertDontSee('Tamara');
    }

    public function test_a_percent_sign_is_searched_literally_instead_of_matching_everyone(): void
    {
        Patient::factory()->create(['first_name' => 'Chikondi', 'last_name' => 'Banda']);

        $this->actingAs($this->nurse())
            ->get('/patients?search=%25')
            ->assertSee('No patients match "%".')
            ->assertDontSee('Chikondi Banda');
    }

    public function test_patient_names_are_escaped_in_search_results(): void
    {
        Patient::factory()->create(['first_name' => '<b>Chikondi</b>']);

        $this->actingAs($this->nurse())
            ->get('/patients')
            ->assertDontSee('<b>Chikondi</b>', false)
            ->assertSee('&lt;b&gt;Chikondi&lt;/b&gt;', false);
    }

    public function test_only_nurses_see_the_register_patient_button(): void
    {
        $this->actingAs($this->nurse())->get('/patients')->assertSee('Register patient');

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/patients')
            ->assertDontSee('Register patient');
    }
}
