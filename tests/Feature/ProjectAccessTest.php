<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $member;

    private User $outsider;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

        $this->admin = Admin::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project = Project::factory()->create(['created_by' => $this->admin->id]);
        $this->project->members()->attach($this->member, ['role' => 'owner']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('projects.show', $this->project))->assertRedirect(route('login'));
    }

    public function test_member_can_view_project(): void
    {
        $this->actingAs($this->member, 'web')
            ->get(route('projects.show', $this->project))
            ->assertOk();
    }

    public function test_outsider_cannot_view_project(): void
    {
        $this->actingAs($this->outsider, 'web')
            ->get(route('projects.show', $this->project))
            ->assertForbidden();
    }

    public function test_member_can_delegate_task(): void
    {
        $this->actingAs($this->member, 'web')
            ->post(route('projects.tasks.store', $this->project), [
                'title' => '实现登录页',
                'description' => '包含表单校验',
                'priority' => 'high',
                'due_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect(route('projects.show', $this->project));

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => '实现登录页',
            'priority' => 'high',
            'created_by' => $this->member->id,
        ]);
    }

    public function test_outsider_cannot_delegate_task(): void
    {
        $this->actingAs($this->outsider, 'web')
            ->post(route('projects.tasks.store', $this->project), [
                'title' => '越权任务',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => '越权任务']);
    }

    public function test_delegating_task_requires_valid_priority(): void
    {
        $this->actingAs($this->member, 'web')
            ->post(route('projects.tasks.store', $this->project), [
                'title' => '非法优先级',
                'priority' => 'urgent',
            ])
            ->assertSessionHasErrors('priority');
    }

    public function test_outsider_cannot_view_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->actingAs($this->outsider, 'web')
            ->get(route('projects.tasks.show', ['project' => $this->project, 'task' => $task]))
            ->assertForbidden();
    }

    public function test_member_can_view_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->actingAs($this->member, 'web')
            ->get(route('projects.tasks.show', ['project' => $this->project, 'task' => $task]))
            ->assertOk();
    }

    public function test_task_of_another_project_not_found_via_scoped_binding(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->admin->id]);
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->actingAs($this->member, 'web')
            ->get(route('projects.tasks.show', ['project' => $otherProject, 'task' => $task]))
            ->assertNotFound();
    }
}
