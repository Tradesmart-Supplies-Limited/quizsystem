<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->getJson('/play/1/api/state')->assertUnauthorized();
    }

    public function test_user_can_log_in_and_out(): void
    {
        $u = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_and_disabled_accounts_are_rejected(): void
    {
        $u = User::factory()->create(['password' => 'secret-pass']);
        $this->post('/login', ['email' => $u->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $u->update(['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $u = User::factory()->create(['password' => 'secret-pass']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $u->email, 'password' => 'bad']);
        }
        $this->post('/login', ['email' => $u->email, 'password' => 'secret-pass'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_facilitators_cannot_reach_admin_only_areas(): void
    {
        $this->actingAs($this->facilitator());
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.settings.edit'))->assertForbidden();
        $this->get(route('admin.questions.index'))->assertOk();
    }

    public function test_admin_can_manage_users_but_not_delete_self(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->post(route('admin.users.store'), [
            'name' => 'Fac', 'email' => 'fac@example.com', 'role' => 'facilitator',
            'password' => 'password123', 'password_confirmation' => 'password123', 'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'fac@example.com', 'role' => 'facilitator']);
        $this->delete(route('admin.users.destroy', $admin))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_role_cannot_be_mass_assigned_through_question_endpoints(): void
    {
        $u = $this->facilitator();
        $this->actingAs($u)->put(route('admin.users.update', $u), ['name' => 'x', 'email' => $u->email, 'role' => 'admin'])->assertForbidden();
        $this->assertSame('facilitator', $u->fresh()->role);
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('quiz:create-admin', ['--name' => 'Boss', '--email' => 'boss@example.com', '--password' => 'longpassword'])->assertSuccessful();
        $this->assertTrue(User::where('email', 'boss@example.com')->first()->isAdmin());
    }
}
