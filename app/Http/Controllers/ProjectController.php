<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\ClientData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $user = $request->user();

        $project->load([
            'members',
            'tasks.creator',
            'tasks.comments.author',
            'devLogs',
            'issues',
            'contracts',
            'payments',
        ]);

        return Inertia::render('Projects/Show', [
            'project' => ClientData::projectDetail($project, $user),
            'tasks' => $project->tasks->map(
                fn ($task) => ClientData::task($task),
            )->values()->all(),
            'dev_logs' => $project->devLogs
                ->sortByDesc('date')
                ->values()
                ->map(fn ($log) => ClientData::devLog($log))
                ->all(),
            'issues' => $project->issues
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($issue) => ClientData::issue($issue))
                ->all(),
            'contracts' => $project->contracts->map(
                fn ($contract) => ClientData::contract($contract),
            )->values()->all(),
            'payments' => $project->canViewPriceFor($user)
                ? $project->payments
                    ->sortByDesc('date')
                    ->values()
                    ->map(fn ($payment) => ClientData::payment($payment))
                    ->all()
                : [],
        ]);
    }
}
