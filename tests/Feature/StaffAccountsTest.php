<?php

namespace Tests\Feature;

use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A valid "Add staff" form.
     *
     * @return array{name: string, email: string, role: string, password: string, password_confirmation: string}
     */
    private function validStaffForm(array $overrides = []): array
    {
        return [
            'name' => 'Grace Phiri',
            'email' => 'grace.phiri@chitawira.test',
            'role' => 'nurse',
            'password' => 'Clinic-Pass-2026',
            'password_confirmation' => 'Clinic-Pass-2026',
            ...$overrides,
        ];
    }

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    public function test_guests_are_redirected_to_login_from_the_staff_pages(): void
    {
        $this->get('/staff')->assertRedirect('/login');
    }

    public function test_non_admins_cannot_view_the_staff_list(): void
    {
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->actingAs($doctor)->get('/staff')->assertForbidden();
    }

    public function test_non_admins_cannot_create_staff_accounts(): void
    {
        $nurse = User::factory()->role(Role::Nurse)->create();

        $this->actingAs($nurse)
            ->post('/staff', $this->validStaffForm())
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'grace.phiri@chitawira.test']);
    }

    public function test_non_admins_cannot_deactivate_staff_accounts(): void
    {
        $nurse = User::factory()->role(Role::Nurse)->create();
        $doctor = User::factory()->role(Role::Doctor)->create();

        $this->actingAs($nurse)
            ->delete("/staff/{$doctor->id}/activation")
            ->assertForbidden();

        $this->assertTrue($doctor->fresh()->is_active);
    }

    public function test_admin_sees_every_staff_member_with_their_role_and_status(): void
    {
        $admin = $this->admin();
        User::factory()->role(Role::Doctor)->create(['name' => 'Dr Mwale']);
        User::factory()->role(Role::Nurse)->inactive()->create(['name' => 'Nurse Kumwenda']);

        $this->actingAs($admin)
            ->get('/staff')
            ->assertOk()
            ->assertSeeInOrder(['Dr Mwale', 'Doctor', 'Active'])
            ->assertSeeInOrder(['Nurse Kumwenda', 'Nurse', 'Deactivated']);
    }

    public function test_staff_names_are_escaped_on_the_staff_list(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => '<script>alert(1)</script>']);

        $this->actingAs($admin)
            ->get('/staff')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_only_admins_see_the_staff_accounts_menu_link(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertSee('Staff accounts');

        $this->actingAs(User::factory()->role(Role::Doctor)->create())
            ->get('/dashboard')
            ->assertDontSee('Staff accounts');
    }

    public function test_admin_can_create_a_staff_account_that_can_log_in(): void
    {
        $this->actingAs($this->admin())
            ->post('/staff', $this->validStaffForm(['email' => 'Grace.Phiri@Chitawira.test']))
            ->assertRedirect('/staff')
            ->assertSessionHas('status', 'Grace Phiri was added as Nurse.');

        $staff = User::where('email', 'grace.phiri@chitawira.test')->firstOrFail();

        $this->assertSame('Grace Phiri', $staff->name);
        $this->assertSame(Role::Nurse, $staff->role);
        $this->assertTrue($staff->is_active);
        $this->assertNotSame('Clinic-Pass-2026', $staff->password);
        $this->assertTrue(Hash::check('Clinic-Pass-2026', $staff->password));

        Auth::guard('web')->logout();

        $this->post('/login', [
            'email' => 'grace.phiri@chitawira.test',
            'password' => 'Clinic-Pass-2026',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($staff);
    }

    public function test_all_fields_are_required_when_creating_staff(): void
    {
        $this->actingAs($this->admin())
            ->post('/staff', [])
            ->assertSessionHasErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field is required.',
                'role' => 'The role field is required.',
                'password' => 'The password field is required.',
            ]);
    }

    public function test_an_email_already_in_use_is_rejected_regardless_of_capitals(): void
    {
        User::factory()->create(['email' => 'grace.phiri@chitawira.test']);

        $this->actingAs($this->admin())
            ->post('/staff', $this->validStaffForm(['email' => 'GRACE.PHIRI@chitawira.test']))
            ->assertSessionHasErrors(['email' => 'The email has already been taken.']);

        $this->assertSame(1, User::where('email', 'grace.phiri@chitawira.test')->count());
    }

    public function test_a_role_outside_the_three_staff_roles_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/staff', $this->validStaffForm(['role' => 'receptionist']))
            ->assertSessionHasErrors(['role' => 'The selected role is invalid.']);
    }

    public function test_the_password_must_be_typed_the_same_twice(): void
    {
        $this->actingAs($this->admin())
            ->post('/staff', $this->validStaffForm(['password_confirmation' => 'Something-Else-1']))
            ->assertSessionHasErrors(['password' => 'The password field confirmation does not match.']);
    }

    public function test_admin_can_deactivate_a_staff_account(): void
    {
        $doctor = User::factory()->role(Role::Doctor)->create(['name' => 'Dr Mwale']);

        $this->actingAs($this->admin())
            ->from('/staff')
            ->delete("/staff/{$doctor->id}/activation")
            ->assertRedirect('/staff')
            ->assertSessionHas('status', 'Dr Mwale has been deactivated.');

        $this->assertFalse($doctor->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from('/staff')
            ->delete("/staff/{$admin->id}/activation")
            ->assertRedirect('/staff')
            ->assertSessionHasErrors(['staff' => 'You cannot deactivate your own account.']);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_can_reactivate_a_deactivated_account(): void
    {
        $nurse = User::factory()->role(Role::Nurse)->inactive()->create(['name' => 'Nurse Kumwenda']);

        $this->actingAs($this->admin())
            ->from('/staff')
            ->post("/staff/{$nurse->id}/activation")
            ->assertRedirect('/staff')
            ->assertSessionHas('status', 'Nurse Kumwenda can log in again.');

        $this->assertTrue($nurse->fresh()->is_active);
    }
}
