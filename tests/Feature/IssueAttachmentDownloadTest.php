<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IssueAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $member;

    private User $outsider;

    private Project $project;

    private Issue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = Admin::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project = Project::factory()->create(['created_by' => $this->admin->id]);
        $this->project->members()->attach($this->member, ['role' => 'member']);

        Storage::disk('local')->put('issue-attachments/test-issue.png', 'issue attachment contents');

        $this->issue = Issue::create([
            'project_id' => $this->project->id,
            'title' => '无法点击登录按钮',
            'description' => '点击登录按钮没有任何反应',
            'attachment_path' => 'issue-attachments/test-issue.png',
            'severity' => \App\Enums\IssueSeverity::Normal,
            'status' => \App\Enums\IssueStatus::Open,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_member_can_download_issue_attachment(): void
    {
        $response = $this->actingAs($this->member, 'web')
            ->get(route('projects.issues.attachment.download', ['project' => $this->project, 'issue' => $this->issue]));

        $response->assertOk();
        $this->assertEquals('issue attachment contents', $response->streamedContent());
    }

    public function test_admin_can_download_issue_attachment(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('projects.issues.attachment.download', ['project' => $this->project, 'issue' => $this->issue]));

        $response->assertOk();
        $this->assertEquals('issue attachment contents', $response->streamedContent());
    }

    public function test_outsider_cannot_download_issue_attachment(): void
    {
        $this->actingAs($this->outsider, 'web')
            ->get(route('projects.issues.attachment.download', ['project' => $this->project, 'issue' => $this->issue]))
            ->assertForbidden();
    }

    public function test_guest_cannot_download_issue_attachment(): void
    {
        $this->get(route('projects.issues.attachment.download', ['project' => $this->project, 'issue' => $this->issue]))
            ->assertRedirect(route('login'));
    }

    public function test_missing_file_returns_404(): void
    {
        Storage::disk('local')->delete('issue-attachments/test-issue.png');

        $response = $this->actingAs($this->member, 'web')
            ->get(route('projects.issues.attachment.download', ['project' => $this->project, 'issue' => $this->issue]));

        $response->assertNotFound();
    }

    public function test_issue_of_another_project_not_found(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->member, 'web')
            ->get(route('projects.issues.attachment.download', ['project' => $otherProject, 'issue' => $this->issue]))
            ->assertNotFound();
    }
}
