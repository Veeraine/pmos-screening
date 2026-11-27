<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_the_login_form_asks_browsers_not_to_fill_in_saved_logins(): void
    {
        $html = $this->get('/login')->getContent();

        $this->assertMatchesRegularExpression('/<form[^>]*action="[^"]*\/login"[^>]*autocomplete="off"/', $html);
        $this->assertMatchesRegularExpression('/name="email"[^>]*autocomplete="off"/', $html);
        $this->assertMatchesRegularExpression('/name="password"[^>]*autocomplete="new-password"/', $html);
    }

    public function test_the_login_form_has_no_remember_me_option(): void
    {
        $this->get('/login')
            ->assertDontSee('Remember me')
            ->assertDontSee('name="remember"', false);
    }

    public function test_logins_are_never_remembered_even_if_asked(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->remember_token, $user->fresh()->remember_token);
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_the_login_form_is_empty_after_logging_out(): void
    {
        $user = User::factory()->create(['email' => 'admin@chitawira.test']);

        $this->actingAs($user)->post('/logout');

        $this->get('/login')
            ->assertDontSee('admin@chitawira.test')
            ->assertDontSee('name="email" value="', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
