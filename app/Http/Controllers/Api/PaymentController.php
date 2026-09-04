<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Project;
use App\Support\CurrentAdmin;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $payments = $project->payments()->with('creator')->latest('date')->paginate(50);

        return response()->json([
            'data' => $payments->map(fn (Payment $payment): array => $this->toArray($payment))->values()->all(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $payload = $request->all();
        $payload['project_id'] = $project->id;
        $payload['date'] ??= now()->toDateString();
        $validated = validator($payload, InputSchemaRegistry::rules('project_payments', 'create'))->validate();
        $adminId = CurrentAdmin::requiredId($request, 'created_by');

        $payment = $project->payments()->create([
            ...$validated,
            'created_by' => $adminId,
        ]);

        return response()->json($this->toArray($payment->load('creator')), 201);
    }

    public function update(Request $request, Project $project, Payment $payment): JsonResponse
    {
        abort_unless($payment->project_id === $project->id, 404);

        $payload = $request->all();
        if (array_key_exists('date', $payload) && $payload['date'] === '') {
            $payload['date'] = null;
        }
        $validated = validator($payload, InputSchemaRegistry::rules('project_payments', 'update'))->validate();
        $payment->update($validated);

        return response()->json($this->toArray($payment->refresh()->load('creator')));
    }

    public function destroy(Project $project, Payment $payment): JsonResponse
    {
        abort_unless($payment->project_id === $project->id, 404);
        $payment->delete();

        return response()->json(['message' => 'Payment deleted.']);
    }

    private function toArray(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'project_id' => $payment->project_id,
            'amount' => $payment->amount,
            'date' => $payment->date?->toDateString(),
            'remark' => $payment->remark,
            'created_by' => $payment->creator ? [
                'id' => $payment->creator->id,
                'name' => $payment->creator->name,
            ] : null,
            'created_at' => $payment->created_at?->toISOString(),
            'updated_at' => $payment->updated_at?->toISOString(),
        ];
    }
}
