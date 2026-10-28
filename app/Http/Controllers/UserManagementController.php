<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()->withCount('branches')->orderBy('name')->get();
        $branches = Branch::query()->orderBy('name')->get();

        return view('users.index', compact('users', 'branches'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedUser($request);
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        $user = User::create($validated);
        $this->syncBranches($request, $user);

        return redirect()->route('users.edit', $user)->with('success', 'Pengguna berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        $user->load('branches');
        $branches = Branch::query()->orderBy('name')->get();

        return view('users.edit', compact('user', 'branches'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validatedUser($request, $user, true);
        $newRole = $validated['role'];
        $newActive = (bool) ($validated['is_active'] ?? false);

        if ($user->isOwner() && $this->isLastActiveOwner($user) && ($newRole !== User::ROLE_OWNER || ! $newActive)) {
            return back()->withErrors(['user' => 'Owner terakhir tidak dapat dinonaktifkan atau diubah rolenya.']);
        }

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $this->syncBranches($request, $user);

        return redirect()->route('users.edit', $user)->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->is_active && $user->isOwner() && $this->isLastActiveOwner($user)) {
            return back()->withErrors(['user' => 'Owner terakhir tidak dapat dinonaktifkan.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return redirect()->route('users.index')->with('success', $user->is_active
            ? 'Pengguna berhasil diaktifkan.'
            : 'Pengguna berhasil dinonaktifkan.');
    }

    private function validatedUser(Request $request, ?User $user = null, bool $updating = false): array
    {
        $emailRule = Rule::unique('users', 'email');
        if ($user) {
            $emailRule = $emailRule->ignore($user->getKey());
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => [$updating ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function syncBranches(Request $request, User $user): void
    {
        $branchIds = array_values(array_unique(array_map('intval', $request->input('branch_ids', []))));
        $defaultId = $request->filled('default_branch_id') ? (int) $request->input('default_branch_id') : null;
        $inactiveIds = array_values(array_unique(array_map('intval', $request->input('inactive_branch_ids', []))));
        $branches = Branch::query()->whereIn('id', $branchIds)->get()->keyBy('id');

        if (count($branchIds) !== $branches->count()) {
            abort(422, 'Penugasan cabang tidak valid.');
        }

        if ($branchIds && $defaultId === null) {
            abort(422, 'Pilih cabang default untuk pengguna ini.');
        }

        if ($defaultId !== null && (! isset($branches[$defaultId]) || ! $branches[$defaultId]->is_active || in_array($defaultId, $inactiveIds, true))) {
            abort(422, 'Cabang default harus aktif dan ditugaskan kepada pengguna.');
        }

        $pivot = [];
        foreach ($branchIds as $branchId) {
            $pivot[$branchId] = [
                'is_default' => $defaultId === $branchId,
                'is_active' => ! in_array($branchId, $inactiveIds, true),
            ];
        }

        DB::transaction(function () use ($user, $pivot) {
            $user->branches()->sync($pivot);
        });
    }

    private function isLastActiveOwner(User $user): bool
    {
        return User::query()
            ->where('role', User::ROLE_OWNER)
            ->where('is_active', true)
            ->where('id', '<>', $user->getKey())
            ->doesntExist();
    }
}
