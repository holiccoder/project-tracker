<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPriceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->project = Project::factory()->create([
            'created_by' => $this->admin->id,
            'amount' => 1234.50,
            'paid_amount' => 234.50,
        ]);
    }

    private function projectPayload(User $user): array
    {
        $response = $this->actingAs($user, 'web')
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => hash_file('xxh128', public_path('build/manifest.json')),
            ])
            ->get(route('projects.show', $this->project));

        $response->assertOk();

        return json_decode($response->getContent(), true)['props'];
    }

    public function test_project_prices_are_hidden_by_default(): void
    {
        $user = User::factory()->create();
        $this->project->members()->attach($user, ['role' => 'member']);

        $payload = $this->projectPayload($user);

        $this->assertFalse($payload['project']['can_view_price']);
        $this->assertNull($payload['project']['amount']);
        $this->assertNull($payload['project']['paid_amount']);
        $this->assertNull($payload['project']['unpaid_amount']);
        $this->assertSame([], $payload['payments']);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'can_view_price' => false,
        ]);
    }

    public function test_project_prices_are_visible_to_an_explicitly_enabled_member(): void
    {
        $user = User::factory()->create();
        $this->project->members()->attach($user, [
            'role' => 'member',
            'can_view_price' => true,
        ]);

        $payload = $this->projectPayload($user);

        $this->assertTrue($payload['project']['can_view_price']);
        $this->assertSame('1234.50', $payload['project']['amount']);
        $this->assertSame('234.50', $payload['project']['paid_amount']);
        $this->assertSame('1000.00', $payload['project']['unpaid_amount']);
    }
}
