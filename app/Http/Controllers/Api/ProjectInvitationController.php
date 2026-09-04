<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectInvitationController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $invitations = $project->invitations()->with('project')->latest()->paginate(50);

        return response()->json([
            'data' => $invitations->map(fn (ProjectInvitation $invitation): array => $this->toArray($invitation))->values()->all(),
            'meta' => [
                'current_page' => $invitations->currentPage(),
                'last_page' => $invitations->lastPage(),
                'per_page' => $invitations->perPage(),
                'total' => $invitations->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $payload = $request->all();
        $payload['project_id'] = $project->id;
        $payload['expires_at'] ??= now()->addDays(7)->toDateTimeString();

        $validated = validator($payload, InputSchemaRegistry::rules('project_invitations', 'create'))->validate();
        $invitation = $project->invitations()->create([
            'email' => $validated['email'],
            'expires_at' => $validated['expires_at'],
            'token' => Str::random(32),
        ]);

        return response()->json($this->toArray($invitation->load('project')), 201);
    }

    public function destroy(Project $project, ProjectInvitation $invitation): JsonResponse
    {
        abort_unless($invitation->project_id === $project->id, 404);
        $invitation->delete();

        return response()->json(['message' => 'Invitation deleted.']);
    }

    private function toArray(ProjectInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'project_id' => $invitation->project_id,
            'project_name' => $invitation->project?->name,
            'email' => $invitation->email,
            'invite_link' => url("/projects/invite/{$invitation->token}"),
            'expires_at' => $invitation->expires_at?->toISOString(),
            'is_expired' => $invitation->isExpired(),
            'status' => $invitation->isExpired() ? 'expired' : 'valid',
            'status_label' => $invitation->isExpired() ? 'Expired' : 'Valid',
            'created_at' => $invitation->created_at?->toISOString(),
            'updated_at' => $invitation->updated_at?->toISOString(),
        ];
    }
}
