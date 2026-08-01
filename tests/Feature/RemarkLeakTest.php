<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemarkLeakTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $member;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->member = User::factory()->create([
            'remark' => 'secret-remark-bbq',
        ]);

        $this->project = Project::factory()->create(['created_by' => $this->admin->id]);
        $this->project->members()->attach($this->member, ['role' => 'owner']);
    }

    private function inertiaHeaders(): array
    {
        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
        ];
    }

    public function test_user_model_serialization_hides_remark(): void
    {
        $this->assertArrayNotHasKey('remark', $this->member->toArray());
    }

    public function test_dashboard_payload_does_not_leak_remark(): void
    {
        $response = $this->actingAs($this->member, 'web')
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringNotContainsString('secret-remark-bbq', $response->getContent());
    }

    public function test_project_payload_does_not_leak_remark(): void
    {
        $response = $this->actingAs($this->member, 'web')
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.show', $this->project));

        $response->assertOk();
        $this->assertStringNotContainsString('secret-remark-bbq', $response->getContent());
    }

    public function test_shared_auth_user_does_not_include_remark(): void
    {
        $response = $this->actingAs($this->member, 'web')
            ->withHeaders($this->inertiaHeaders())
            ->get(route('dashboard'));

        $payload = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('remark', $payload['props']['auth']['user']);
        $this->assertSame($this->member->name, $payload['props']['auth']['user']['name']);
    }

    public function test_member_list_in_project_payload_has_no_remark(): void
    {
        $response = $this->actingAs($this->member, 'web')
            ->withHeaders($this->inertiaHeaders())
            ->get(route('projects.show', $this->project));

        $payload = json_decode($response->getContent(), true);

        foreach ($payload['props']['project']['members'] as $member) {
            $this->assertArrayNotHasKey('remark', $member);
        }
    }
}
