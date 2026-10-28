<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_the_redesigned_accessible_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="auth-card"', false)
            ->assertSee('Selamat datang di JOOAL POS')
            ->assertSee('for="login-email"', false)
            ->assertSee('for="login-password"', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('aria-label="Tampilkan kata sandi"', false)
            ->assertSee(route('login.store'), false)
            ->assertSee('name="_token"', false);
    }

    public function test_guest_cannot_access_pos_routes(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/reports')->assertRedirect(route('login'));
        $this->get('/history')->assertRedirect(route('login'));
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('a-secure-test-password'),
            'role' => User::ROLE_OWNER,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'a-secure-test-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_login_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'cashier@example.test',
            'password' => Hash::make('a-secure-test-password'),
        ]);

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'cashier@example.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_role_middleware_rejects_users_without_the_required_role(): void
    {
        Route::get('/test-owner-only', function () {
            return response('ok');
        })->middleware(['auth', 'role:owner']);

        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);

        $this->actingAs($cashier)
            ->get('/test-owner-only')
            ->assertForbidden();

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)
            ->get('/test-owner-only')
            ->assertOk();
    }
}
