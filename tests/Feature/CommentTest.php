<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);
    }

    public function test_authorized_user_can_comment_on_task(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $project->members()->attach($user, ['role' => 'member']);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'created_by' => $user->id,
        ]);

        // User comments
        $response = $this->actingAs($user, 'web')
            ->post(route('tasks.comments.store', $task->id), [
                'body' => '这是一条测试评论',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'commentable_id' => $task->id,
            'commentable_type' => Task::class,
            'body' => '这是一条测试评论',
            'author_id' => $user->id,
            'author_type' => User::class,
        ]);

        // Outsider cannot comment
        $outsider = User::factory()->create();
        $responseOutsider = $this->actingAs($outsider, 'web')
            ->post(route('tasks.comments.store', $task->id), [
                'body' => '这是外部人员的评论',
            ]);

        $responseOutsider->assertStatus(403);
    }
}
