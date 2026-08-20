<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * List accounts.
     *
     * Query parameters:
     * - project_id (optional)
     * - search (optional, searches website_name/username)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Account::query()->with('project:id,name');

        if (! empty($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (! empty($validated['search'])) {
            $search = '%'.addcslashes($validated['search'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('website_name', 'like', $search)->orWhere('username', 'like', $search));
        }

        $accounts = $query->latest('created_at')->paginate(50);

        return response()->json([
            'data' => $accounts->map(fn (Account $account) => $this->toArray($account)),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    /**
     * Show a single account.
     */
    public function show(Account $account): JsonResponse
    {
        return response()->json($this->toArray($account->load('project:id,name')));
    }

    /**
     * Create a new account.
     *
     * Body parameters:
     * - project_id (required)
     * - website_name (required)
     * - login_url (required, url)
     * - username (required)
     * - password (required, stored encrypted)
     * - note (optional)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'website_name' => ['required', 'string', 'max:255'],
            'login_url' => ['required', 'url', 'max:2048'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $account = Account::create($validated);

        return response()->json($this->toArray($account->load('project:id,name')), 201);
    }

    /**
     * Update an account.
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['sometimes', 'required', 'integer', 'exists:projects,id'],
            'website_name' => ['sometimes', 'required', 'string', 'max:255'],
            'login_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'username' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $account->update($validated);

        return response()->json($this->toArray($account->load('project:id,name')));
    }

    /**
     * Delete an account.
     */
    public function destroy(Account $account): JsonResponse
    {
        $account->delete();

        return response()->json(['message' => 'Account deleted.']);
    }

    private function toArray(Account $account): array
    {
        return [
            'id' => $account->id,
            'project_id' => $account->project_id,
            'project_name' => $account->project?->name,
            'website_name' => $account->website_name,
            'login_url' => $account->login_url,
            'username' => $account->username,
            'password' => $account->password,
            'note' => $account->note,
            'created_at' => $account->created_at?->toISOString(),
            'updated_at' => $account->updated_at?->toISOString(),
        ];
    }
}
