<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Admin;
use App\Models\Issue;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentInputParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_resource_create_forms_render_from_the_shared_input_adapter(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        foreach ([
            \App\Filament\Resources\Users\Pages\CreateUser::class,
            \App\Filament\Resources\Projects\Pages\CreateProject::class,
            \App\Filament\Resources\Tasks\Pages\CreateTask::class,
            \App\Filament\Resources\DevLogs\Pages\CreateDevLog::class,
            \App\Filament\Resources\DevLogUpdates\Pages\CreateDevLogUpdate::class,
            \App\Filament\Resources\Issues\Pages\CreateIssue::class,
            \App\Filament\Resources\Contracts\Pages\CreateContract::class,
            \App\Filament\Resources\Accounts\Pages\CreateAccount::class,
        ] as $page) {
            Livewire::test($page)->assertSuccessful();
        }
    }

    public function test_all_relation_manager_input_forms_render(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $admin->id]);
        $task = Task::factory()->create(['project_id' => $project->id]);

        $this->actingAs($admin, 'admin');

        $relations = [
            [
                \App\Filament\Resources\Projects\RelationManagers\MembersRelationManager::class,
                $project,
                \App\Filament\Resources\Projects\Pages\EditProject::class,
            ],
            [
                \App\Filament\Resources\Projects\RelationManagers\PaymentsRelationManager::class,
                $project,
                \App\Filament\Resources\Projects\Pages\EditProject::class,
            ],
            [
                \App\Filament\Resources\Projects\RelationManagers\InvitationsRelationManager::class,
                $project,
                \App\Filament\Resources\Projects\Pages\EditProject::class,
            ],
            [
                \App\Filament\Resources\Projects\RelationManagers\DevLogsRelationManager::class,
                $project,
                \App\Filament\Resources\Projects\Pages\EditProject::class,
            ],
            [
                \App\Filament\Resources\Users\RelationManagers\ProjectsRelationManager::class,
                $user,
                \App\Filament\Resources\Users\Pages\EditUser::class,
            ],
            [
                \App\Filament\Resources\Tasks\RelationManagers\CommentsRelationManager::class,
                $task,
                \App\Filament\Resources\Tasks\Pages\EditTask::class,
            ],
        ];

        foreach ($relations as [$component, $ownerRecord, $pageClass]) {
            Livewire::test($component, compact('ownerRecord', 'pageClass'))->assertSuccessful();
        }
    }
}
