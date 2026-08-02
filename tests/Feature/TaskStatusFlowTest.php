<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStatusFlowTest extends TestCase
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

    public function test_member_can_accept_done_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Done,
            'completed_at' => now(),
        ]);

        $this->actingAs($this->member, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'accept'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::Accepted->value,
        ]);
        $this->assertNotNull($task->fresh()->accepted_at);
    }

    public function test_member_can_request_changes_on_done_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Done,
        ]);

        $this->actingAs($this->member, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'request_changes'])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => TaskStatus::ChangesRequested->value,
        ]);
    }

    public function test_member_cannot_confirm_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Pending,
        ]);

        $this->actingAs($this->member, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'confirm'])
            ->assertForbidden();

        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_outsider_cannot_accept_task(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Done,
        ]);

        $this->actingAs($this->outsider, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'accept'])
            ->assertForbidden();
    }

    public function test_reject_requires_reason(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Pending,
        ]);

        $this->actingAs($this->member, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'reject'])
            ->assertForbidden();
    }

    public function test_illegal_transition_rejected(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Pending,
        ]);

        $this->actingAs($this->member, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'accept'])
            ->assertForbidden();

        $this->assertSame(TaskStatus::Pending, $task->fresh()->status);
    }

    public function test_invalid_action_rejected(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Pending,
        ]);

        $this->actingAs($this->member, 'web')
            ->patch(route('tasks.status.update', $task), ['action' => 'explode'])
            ->assertSessionHasErrors('action');
    }
}
