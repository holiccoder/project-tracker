<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $member;

    private User $outsider;

    private Project $project;

    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = Admin::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->project = Project::factory()->create(['created_by' => $this->admin->id]);
        $this->project->members()->attach($this->member, ['role' => 'owner']);

        Storage::disk('local')->put('contracts/test-contract.pdf', 'contract contents');

        $this->contract = Contract::factory()->create([
            'project_id' => $this->project->id,
            'name' => '服务合同.pdf',
            'file_path' => 'contracts/test-contract.pdf',
            'uploaded_by' => $this->admin->id,
        ]);
    }

    public function test_member_can_download_contract(): void
    {
        $response = $this->actingAs($this->member, 'web')
            ->get(route('projects.contracts.download', ['project' => $this->project, 'contract' => $this->contract]));

        $response->assertOk();
        $response->assertStreamedContent('contract contents');

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString(rawurlencode('服务合同.pdf'), $disposition);
    }

    public function test_outsider_cannot_download_contract(): void
    {
        $this->actingAs($this->outsider, 'web')
            ->get(route('projects.contracts.download', ['project' => $this->project, 'contract' => $this->contract]))
            ->assertForbidden();
    }

    public function test_guest_cannot_download_contract(): void
    {
        $this->get(route('projects.contracts.download', ['project' => $this->project, 'contract' => $this->contract]))
            ->assertRedirect(route('login'));
    }

    public function test_contract_of_another_project_not_found(): void
    {
        $otherProject = Project::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->member, 'web')
            ->get(route('projects.contracts.download', ['project' => $otherProject, 'contract' => $this->contract]))
            ->assertNotFound();
    }
}
