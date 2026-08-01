<?php

namespace App\Http\Controllers;

use App\Support\ClientData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = $user->projects()
            ->withCount([
                'tasks',
                'tasks as tasks_done' => fn ($query) => $query->whereIn('status', ['done', 'accepted']),
            ])
            ->with(['devLogs' => fn ($query) => $query->latest('date')->limit(1)])
            ->latest()
            ->get();

        $data = $projects->map(function ($project) use ($user) {
            $summary = ClientData::projectSummary($project, $user);
            $latestLog = $project->devLogs->first();

            $summary['last_log'] = $latestLog
                ? [
                    'date' => $latestLog->date->toDateString(),
                    'content' => mb_strimwidth($latestLog->content, 0, 60, '…'),
                ]
                : null;

            return $summary;
        })->values()->all();

        return Inertia::render('Dashboard', [
            'projects' => $data,
        ]);
    }
}
