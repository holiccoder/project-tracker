<?php

namespace Tests\Feature;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_create_task_form_without_record_type_error(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateTask::class)
            ->assertSuccessful();
    }

    public function test_can_render_dev_logs_relation_manager_with_batch_repeater(): void
    {
        $admin = Admin::factory()->create();
        $project = \App\Models\Project::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin, 'admin');

        Livewire::test(\App\Filament\Resources\Projects\RelationManagers\DevLogsRelationManager::class, [
            'ownerRecord' => $project,
            'pageClass' => \App\Filament\Resources\Projects\Pages\EditProject::class,
        ])
            ->assertSuccessful();
    }
}
