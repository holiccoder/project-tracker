<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
    }

    public function test_accept_invitation_with_invalid_token(): void
    {
        $response = $this->get(route('projects.invite.accept', 'invalid-token'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', '邀请链接无效');
    }

    public function test_accept_invitation_with_expired_token(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => 'test@test.com',
            'token' => 'expired-token',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->get(route('projects.invite.accept', 'expired-token'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', '邀请链接已过期');
    }

    public function test_unauthenticated_user_accepting_invitation_saves_token_in_session(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => 'test@test.com',
            'token' => 'valid-token',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->get(route('projects.invite.accept', 'valid-token'));

        $response->assertRedirect('/');
        $this->assertEquals('valid-token', session('pending_invitation_token'));
    }

    public function test_authenticated_user_with_matching_email_joins_project(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $user = User::factory()->create(['email' => 'test@test.com']);

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => 'test@test.com',
            'token' => 'valid-token',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('projects.invite.accept', 'valid-token'));

        $response->assertRedirect(route('projects.show', $project->slug));
        $this->assertTrue($project->hasMember($user));
        $this->assertDatabaseMissing('project_invitations', ['id' => $invitation->id]);
    }

    public function test_authenticated_user_with_mismatched_email_cannot_join_project(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $user = User::factory()->create(['email' => 'wrong@example.com']);

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => 'test@test.com',
            'token' => 'valid-token',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->get(route('projects.invite.accept', 'valid-token'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', '此邀请链接属于另一个邮箱账号');
        $this->assertFalse($project->hasMember($user));
        $this->assertDatabaseHas('project_invitations', ['id' => $invitation->id]);
    }

    public function test_login_attaches_pending_invitation_if_email_matches(): void
    {
        $this->withoutExceptionHandling();
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $user = User::factory()->create(['email' => 'test@test.com']);

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => 'test@test.com',
            'token' => 'valid-token',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withSession(['pending_invitation_token' => 'valid-token'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertTrue($project->hasMember($user));
        $this->assertDatabaseMissing('project_invitations', ['id' => $invitation->id]);
        $response->assertSessionMissing('pending_invitation_token');
    }

    public function test_login_ignores_pending_invitation_if_email_mismatch(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $user = User::factory()->create(['email' => 'wrong@example.com']);

        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => 'test@test.com',
            'token' => 'valid-token',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->withSession(['pending_invitation_token' => 'valid-token'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertFalse($project->hasMember($user));
        $this->assertDatabaseHas('project_invitations', ['id' => $invitation->id]);
        $response->assertSessionMissing('pending_invitation_token');
    }
}
