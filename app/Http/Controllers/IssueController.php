<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class IssueController extends Controller
{
    public function downloadAttachment(Project $project, Issue $issue): Response
    {
        // Authorization check: must be member of project or admin (developer)
        $user = auth('web')->user();
        $isAdmin = auth('admin')->check();

        if (!$isAdmin && (!$user || !$project->hasMember($user))) {
            abort(403, '你没有权限下载该问题的附件。');
        }

        abort_unless($issue->project_id === $project->id, 404);
        abort_unless($issue->attachment_path !== null, 404);

        if (!Storage::disk('local')->exists($issue->attachment_path)) {
            abort(404, '附件不存在');
        }

        return Storage::disk('local')->download($issue->attachment_path, basename($issue->attachment_path));
    }
}
