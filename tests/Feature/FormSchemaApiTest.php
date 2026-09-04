<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FormSchemaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.api_token' => 'test-api-token']);
    }

    public function test_form_schema_is_authenticated_and_preserves_filament_field_order(): void
    {
        $this->getJson('/api/form-schemas')->assertUnauthorized();

        $schema = $this->withToken('test-api-token')
            ->getJson('/api/form-schemas')
            ->assertOk()
            ->json();

        $this->assertSame(
            ['name', 'email', 'wechat', 'phone', 'remark', 'password'],
            array_keys($schema['models']['users']['fields']),
        );
        $this->assertSame(
            ['name', 'slug', 'description', 'members', 'status', 'amount', 'paid_amount', 'unpaid_amount', 'deadline', 'repo_url', 'remark'],
            array_keys($schema['models']['projects']['fields']),
        );
        $this->assertSame(
            ['project_id', 'title', 'description', 'attachments', 'priority', 'status', 'reject_reason'],
            array_keys($schema['models']['tasks']['fields']),
        );
        $this->assertTrue($schema['models']['tasks']['fields']['status']['read_only']);
        $this->assertSame('pending', $schema['models']['tasks']['fields']['status']['default']);
        $this->assertSame('attachment', $schema['models']['issues']['fields']['attachment_path']['api_transport_key']);
        $this->assertSame('file', $schema['models']['contracts']['fields']['file_path']['api_transport_key']);
        $this->assertSame(
            ['confirm', 'reject', 'start', 'restart', 'complete'],
            array_column($schema['actions']['tasks'], 'name'),
        );
        $this->assertSame(
            ['start', 'resolve', 'close'],
            array_column($schema['actions']['issues'], 'name'),
        );
        $this->assertSame(
            ['user_id', 'role', 'can_view_price'],
            array_keys($schema['relations']['project_members']['fields']),
        );
        $this->assertNotEmpty($schema['filters']['tasks'][1]['options']);
    }

    public function test_user_and_project_inputs_accept_nullable_clearing_and_project_members(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        $createdUser = $this->withToken('test-api-token')
            ->postJson('/api/users', [
                'name' => 'New customer',
                'email' => 'new-customer@example.com',
                'wechat' => 'wx-id',
                'phone' => '123',
                'remark' => 'internal',
                'password' => 'secret',
            ])
            ->assertCreated()
            ->json();

        $this->withToken('test-api-token')
            ->patchJson('/api/users/'.$createdUser['id'], [
                'wechat' => null,
                'phone' => null,
                'remark' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $createdUser['id'],
            'wechat' => null,
            'phone' => null,
            'remark' => null,
        ]);

        $project = $this->withToken('test-api-token')
            ->postJson('/api/projects', [
                'name' => 'Schema Project',
                'description' => 'Description',
                'members' => [$user->id],
                'amount' => 100,
                'created_by' => $admin->id,
            ])
            ->assertCreated()
            ->json();

        $this->assertMatchesRegularExpression('/^schema-project-[a-z0-9]{6}$/', $project['slug']);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project['id'],
            'user_id' => $user->id,
        ]);

        $this->withToken('test-api-token')
            ->patchJson('/api/projects/'.$project['id'], [
                'description' => null,
                'remark' => null,
                'members' => [],
            ])
            ->assertOk();

        $this->assertDatabaseHas('projects', ['id' => $project['id'], 'description' => null, 'remark' => null]);
        $this->assertDatabaseMissing('project_user', ['project_id' => $project['id'], 'user_id' => $user->id]);
    }

    public function test_task_and_issue_statuses_only_change_through_legal_actions(): void
    {
        $admin = Admin::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $task = $this->withToken('test-api-token')
            ->postJson('/api/tasks', [
                'project_id' => $project->id,
                'title' => 'Stateful task',
            ])
            ->assertCreated()
            ->json();

        $this->withToken('test-api-token')
            ->patchJson('/api/tasks/'.$task['id'], ['status' => 'done', 'title' => 'Renamed'])
            ->assertOk();
        $this->assertSame('pending', Task::findOrFail($task['id'])->status->value);

        $this->withToken('test-api-token')
            ->patchJson('/api/tasks/'.$task['id'].'/status', ['action' => 'reject'])
            ->assertStatus(422);

        $this->withToken('test-api-token')
            ->patchJson('/api/tasks/'.$task['id'].'/status', ['action' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');

        $issue = $this->withToken('test-api-token')
            ->postJson('/api/issues', [
                'project_id' => $project->id,
                'title' => 'Stateful issue',
                'created_by' => $admin->id,
            ])
            ->assertCreated()
            ->json();

        $this->withToken('test-api-token')
            ->patchJson('/api/issues/'.$issue['id'].'/status', ['action' => 'resolve'])
            ->assertStatus(422);

        $this->withToken('test-api-token')
            ->patchJson('/api/issues/'.$issue['id'].'/status', ['action' => 'start'])
            ->assertOk()
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_relation_workflows_and_file_transport_are_available(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);

        $this->withToken('test-api-token')
            ->postJson("/api/projects/{$project->slug}/members", [
                'user_id' => $user->id,
                'role' => 'member',
                'can_view_price' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('can_view_price', true);

        $this->withToken('test-api-token')
            ->postJson("/api/projects/{$project->slug}/payments", [
                'amount' => 25,
                'created_by' => $admin->id,
            ])
            ->assertCreated();
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'paid_amount' => '25.00']);

        $this->withToken('test-api-token')
            ->postJson("/api/projects/{$project->slug}/invitations", [
                'email' => 'invite@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'invite@example.com')
            ->assertJsonStructure(['invite_link', 'expires_at']);

        $task = Task::factory()->create(['project_id' => $project->id]);
        $this->withToken('test-api-token')
            ->postJson("/api/tasks/{$task->id}/comments", [
                'body' => 'Admin comment',
                'author_id' => $admin->id,
            ])
            ->assertCreated()
            ->assertJsonPath('body', 'Admin comment');

        $this->withToken('test-api-token')
            ->post("/api/tasks/{$task->id}", [
                '_method' => 'PUT',
                'title' => $task->title,
                'attachments' => [UploadedFile::fake()->create('readme.txt', 4, 'text/plain')],
            ], ['Accept' => 'application/json', 'Authorization' => 'Bearer test-api-token'])
            ->assertOk()
            ->assertJsonCount(1, 'attachments');

        $this->withToken('test-api-token')
            ->post("/api/projects/{$project->id}/dev-logs/batch", [
                'logs' => [[
                    'date' => now()->toDateString(),
                    'status' => 'in_progress',
                    'category' => 'agent_independent',
                    'content' => 'Batch entry',
                ]],
            ], ['Accept' => 'application/json', 'Authorization' => 'Bearer test-api-token'])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $contract = $this->withToken('test-api-token')
            ->post('/api/contracts', [
                'project_id' => $project->id,
                'name' => 'agreement.pdf',
                'file' => UploadedFile::fake()->create('agreement.pdf', 4, 'application/pdf'),
                'uploaded_by' => $admin->id,
            ], ['Accept' => 'application/json', 'Authorization' => 'Bearer test-api-token'])
            ->assertCreated()
            ->assertJsonPath('name', 'agreement.pdf')
            ->json();

        $this->withToken('test-api-token')
            ->get('/api/contracts/'.$contract['id'].'/download')
            ->assertOk();
    }
}
