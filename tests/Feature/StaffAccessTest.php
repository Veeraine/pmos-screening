<?php

namespace Tests\Feature;

use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tests that only logged-in, active staff with the right role can open each page.
 */

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:doctor'])
            ->get('/_test/doctors-only', fn () => 'ok');

        Route::middleware(['web', 'auth', 'role:nurse,doctor'])
            ->get('/_test/clinical-staff', fn () => 'ok');
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_dashboard_shows_the_users_role(): void
    {
        $doctor = User::factory()->role(Role::Doctor)->create(['name' => 'Dr Banda']);

        $this->actingAs($doctor)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dr Banda')
            ->assertSee('Doctor');
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_deactivated_while_logged_in_are_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_only_users_with_an_allowed_role_can_open_a_restricted_page(): void
    {
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->actingAs($doctor)->get('/_test/doctors-only')->assertOk();

        foreach ([Role::Admin, Role::Nurse] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/_test/doctors-only')
                ->assertForbidden();
        }
    }

    public function test_a_page_can_allow_several_roles(): void
    {
        $this->actingAs(User::factory()->role(Role::Nurse)->create())
            ->get('/_test/clinical-staff')
            ->assertOk();

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/_test/clinical-staff')
            ->assertOk();

        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get('/_test/clinical-staff')
            ->assertForbidden();
    }
}
