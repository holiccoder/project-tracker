<?php

namespace Tests\Feature;

use App\Models\DevLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevLogUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.api_token' => 'test-api-token']);
    }

    public function test_an_update_can_be_added_to_a_dev_log(): void
    {
        $devLog = DevLog::factory()->create();

        $response = $this->withToken('test-api-token')
            ->postJson("/api/dev-logs/{$devLog->id}/updates", [
                'update' => '已补充接口测试结果。',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('dev_log_id', $devLog->id)
            ->assertJsonPath('update', '已补充接口测试结果。');

        $this->assertDatabaseHas('dev_log_updates', [
            'dev_log_id' => $devLog->id,
            'update' => '已补充接口测试结果。',
        ]);
    }

    public function test_a_dev_log_update_can_be_updated(): void
    {
        $devLog = DevLog::factory()->create();
        $update = $devLog->updates()->create(['update' => '初始更新']);

        $response = $this->withToken('test-api-token')
            ->patchJson("/api/dev-logs/{$devLog->id}/updates/{$update->id}", [
                'update' => '更新后的内容',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('id', $update->id)
            ->assertJsonPath('update', '更新后的内容');

        $this->assertDatabaseHas('dev_log_updates', [
            'id' => $update->id,
            'update' => '更新后的内容',
        ]);
    }

    public function test_dev_log_list_includes_the_most_recent_update(): void
    {
        $devLog = DevLog::factory()->create();
        $devLog->updates()->create(['update' => '第一次更新']);
        $devLog->updates()->create(['update' => '最近一次更新']);

        $this->withToken('test-api-token')
            ->getJson('/api/dev-logs')
            ->assertOk()
            ->assertJsonPath('data.0.latest_update.update', '最近一次更新');
    }

    public function test_an_update_cannot_be_changed_through_another_dev_log(): void
    {
        $devLog = DevLog::factory()->create();
        $otherDevLog = DevLog::factory()->create();
        $update = $otherDevLog->updates()->create(['update' => '其他日志的更新']);

        $this->withToken('test-api-token')
            ->patchJson("/api/dev-logs/{$devLog->id}/updates/{$update->id}", [
                'update' => '越权修改',
            ])
            ->assertNotFound();
    }
}
