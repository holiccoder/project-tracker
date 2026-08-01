<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\Contract;
use App\Models\DevLog;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $outsider;

    private Admin $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project = Project::factory()->create(['created_by' => $this->admin->id]);
        $this->project->members()->attach($this->member, ['role' => 'owner']);
    }

    public function test_project_policy(): void
    {
        $this->assertTrue($this->member->can('view', $this->project));
        $this->assertFalse($this->outsider->can('view', $this->project));
        $this->assertFalse($this->member->can('create', Project::class));
        $this->assertFalse($this->member->can('update', $this->project));
        $this->assertFalse($this->member->can('delete', $this->project));

        $this->assertTrue($this->admin->can('view', $this->project));
        $this->assertTrue($this->admin->can('update', $this->project));
        $this->assertTrue($this->admin->can('delete', $this->project));
    }

    public function test_task_policy(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->member->id,
        ]);

        $this->assertTrue($this->member->can('view', $task));
        $this->assertFalse($this->outsider->can('view', $task));
        $this->assertTrue($this->member->can('create', [Task::class, $this->project]));
        $this->assertFalse($this->outsider->can('create', [Task::class, $this->project]));
        $this->assertFalse($this->member->can('update', $task));
        $this->assertFalse($this->member->can('delete', $task));
    }

    public function test_task_update_status_actions(): void
    {
        $task = Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => TaskStatus::Pending,
        ]);

        $this->assertTrue($this->admin->can('updateStatus', [$task, 'confirm']));
        $this->assertTrue($this->admin->can('updateStatus', [$task, 'reject']));
        $this->assertFalse($this->member->can('updateStatus', [$task, 'confirm']));
        $this->assertFalse($this->outsider->can('updateStatus', [$task, 'accept']));

        $task->status = TaskStatus::Done;
        $task->save();

        $this->assertTrue($this->member->can('updateStatus', [$task, 'accept']));
        $this->assertTrue($this->member->can('updateStatus', [$task, 'request_changes']));
        $this->assertFalse($this->outsider->can('updateStatus', [$task, 'accept']));
    }

    public function test_dev_log_policy_is_read_only_for_customers(): void
    {
        $log = DevLog::factory()->create(['project_id' => $this->project->id]);

        $this->assertTrue($this->member->can('view', $log));
        $this->assertFalse($this->outsider->can('view', $log));
        $this->assertFalse($this->member->can('create', DevLog::class));
        $this->assertFalse($this->member->can('update', $log));
        $this->assertFalse($this->member->can('delete', $log));

        $this->assertTrue($this->admin->can('create', DevLog::class));
        $this->assertTrue($this->admin->can('update', $log));
    }

    public function test_issue_policy_is_read_only_for_customers(): void
    {
        $issue = Issue::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertTrue($this->member->can('view', $issue));
        $this->assertFalse($this->outsider->can('view', $issue));
        $this->assertFalse($this->member->can('create', Issue::class));
        $this->assertFalse($this->member->can('update', $issue));

        $this->assertTrue($this->admin->can('create', Issue::class));
    }

    public function test_contract_policy(): void
    {
        $contract = Contract::factory()->create([
            'project_id' => $this->project->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $this->assertTrue($this->member->can('view', $contract));
        $this->assertFalse($this->outsider->can('view', $contract));
        $this->assertFalse($this->member->can('create', Contract::class));
        $this->assertFalse($this->member->can('delete', $contract));

        $this->assertTrue($this->admin->can('view', $contract));
        $this->assertTrue($this->admin->can('create', Contract::class));
    }
}
