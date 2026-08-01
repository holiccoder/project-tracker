<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Project;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function download(Project $project, Contract $contract): StreamedResponse
    {
        $this->authorize('view', $contract);

        abort_unless($project->id === $contract->project_id, 404);

        try {
            return Storage::disk('local')->download($contract->file_path, $contract->name);
        } catch (FileNotFoundException) {
            abort(404, '合同文件不存在');
        }
    }
}
