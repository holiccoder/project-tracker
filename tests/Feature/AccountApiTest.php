<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Admin;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.api_token' => 'test-api-token']);
    }

    public function test_an_admin_can_log_in_and_use_the_issued_token(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('admin.id', $admin->id)
            ->assertJsonStructure(['token', 'admin' => ['id', 'name', 'email']]);

        $this->withToken($response->json('token'))
            ->getJson('/api/accounts')
            ->assertOk();
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        Admin::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_accounts_require_authentication(): void
    {
        $this->getJson('/api/accounts')->assertUnauthorized();
    }

    public function test_an_account_can_be_created_and_password_is_stored_encrypted(): void
    {
        $project = Project::factory()->create();

        $response = $this->withToken('test-api-token')
            ->postJson('/api/accounts', [
                'project_id' => $project->id,
                'website_name' => '客户后台',
                'login_url' => 'https://example.com/login',
                'username' => 'boss',
                'password' => 'plain-secret',
                'note' => '测试备注',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('website_name', '客户后台')
            ->assertJsonPath('project_name', $project->name)
            ->assertJsonPath('password', 'plain-secret');

        $account = Account::firstOrFail();
        $this->assertSame('plain-secret', $account->password);
        $this->assertNotSame('plain-secret', $account->getRawOriginal('password'));
    }

    public function test_accounts_can_be_filtered_by_project(): void
    {
        $project = Project::factory()->create();
        $other = Project::factory()->create();
        Account::factory()->count(2)->create(['project_id' => $project->id]);
        Account::factory()->create(['project_id' => $other->id]);

        $this->withToken('test-api-token')
            ->getJson("/api/accounts?project_id={$project->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_account_can_be_updated(): void
    {
        $account = Account::factory()->create();

        $this->withToken('test-api-token')
            ->putJson("/api/accounts/{$account->id}", [
                'username' => 'new-name',
                'password' => 'new-secret',
            ])
            ->assertOk()
            ->assertJsonPath('username', 'new-name')
            ->assertJsonPath('password', 'new-secret');
    }

    public function test_an_account_can_be_deleted(): void
    {
        $account = Account::factory()->create();

        $this->withToken('test-api-token')
            ->deleteJson("/api/accounts/{$account->id}")
            ->assertOk();

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }
}
