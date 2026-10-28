<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_user_management_and_other_roles_are_denied(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/users')->assertOk()->assertSee('Manajemen Pengguna');

        foreach ([User::ROLE_MANAGER, User::ROLE_CASHIER, User::ROLE_INVENTORY] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/users')
                ->assertForbidden();
        }
    }

    public function test_owner_can_create_and_update_user_with_branch_assignments(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $first = $this->branch('JKT-01', true);
        $second = $this->branch('BDG-01', true);
        $third = $this->branch('SBY-01', true);

        $this->actingAs($owner)
            ->post('/users', [
                'name' => 'Kasir JOOAL',
                'email' => 'kasir@example.test',
                'role' => User::ROLE_CASHIER,
                'password' => 'a-secure-password',
                'password_confirmation' => 'a-secure-password',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'kasir@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('a-secure-password', $user->password));

        $this->actingAs($owner)
            ->put('/users/'.$user->id, [
                'name' => 'Kasir JOOAL Updated',
                'email' => 'kasir.updated@example.test',
                'role' => User::ROLE_MANAGER,
                'password' => '',
                'password_confirmation' => '',
                'is_active' => 1,
                'branch_ids' => [$first->id, $second->id, $third->id],
                'default_branch_id' => $second->id,
                'inactive_branch_ids' => [$third->id],
            ])
            ->assertRedirect(route('users.edit', $user));

        $user->refresh()->load('branches');
        $this->assertSame('Kasir JOOAL Updated', $user->name);
        $this->assertSame(User::ROLE_MANAGER, $user->role);
        $this->assertEqualsCanonicalizing([$first->id, $second->id, $third->id], $user->branches->pluck('id')->all());
        $this->assertTrue((bool) $user->branches->firstWhere('id', $second->id)->pivot->is_default);
        $this->assertFalse((bool) $user->branches->firstWhere('id', $third->id)->pivot->is_active);

        $this->actingAs($user)->post('/branches/select', ['branch_id' => $third->id])->assertForbidden();
    }

    public function test_user_validation_rejects_duplicate_email_and_invalid_role(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $existing = User::factory()->create(['email' => 'existing@example.test']);

        $this->actingAs($owner)->from('/users')->post('/users', [
            'name' => 'Another User',
            'email' => $existing->email,
            'role' => 'superadmin',
            'password' => 'a-secure-password',
            'password_confirmation' => 'a-secure-password',
        ])->assertRedirect('/users')->assertSessionHasErrors(['email', 'role']);
    }

    public function test_default_branch_must_be_active_and_assigned(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $active = $this->branch('JKT-01', true);
        $inactive = $this->branch('BDG-01', false);

        $this->actingAs($owner)->put('/users/'.$user->id, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => 1,
            'branch_ids' => [$active->id],
            'default_branch_id' => $inactive->id,
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login_or_continue_existing_session(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('a-secure-password'),
            'is_active' => false,
        ]);

        $this->from(route('login'))->post('/login', [
            'email' => $user->email,
            'password' => 'a-secure-password',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $activeUser = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($activeUser);
        $activeUser->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_last_active_owner_cannot_be_disabled_or_demoted(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->patch('/users/'.$owner->id.'/toggle-status')
            ->assertRedirect()->assertSessionHasErrors('user');
        $this->assertTrue($owner->fresh()->is_active);

        $this->actingAs($owner)->put('/users/'.$owner->id, [
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => User::ROLE_MANAGER,
            'is_active' => 1,
        ])->assertRedirect()->assertSessionHasErrors('user');
        $this->assertSame(User::ROLE_OWNER, $owner->fresh()->role);
    }

    private function branch(string $code, bool $active): Branch
    {
        return Branch::create([
            'code' => $code,
            'name' => 'JOOAL '.$code,
            'timezone' => 'Asia/Jakarta',
            'is_active' => $active,
        ]);
    }
}
