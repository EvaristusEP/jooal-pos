<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;

class BranchContext
{
    public const SESSION_KEY = 'active_branch_id';

    public function availableFor(User $user): Collection
    {
        if ($user->isOwner()) {
            return Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        return $user->branches()
            ->where('branches.is_active', true)
            ->wherePivot('is_active', true)
            ->orderBy('branches.name')
            ->get();
    }

    public function canAccess(User $user, Branch $branch): bool
    {
        if (! $branch->is_active) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        return $user->branches()
            ->whereKey($branch->getKey())
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function activeFor(User $user): ?Branch
    {
        $branchId = session(self::SESSION_KEY);

        if ($branchId) {
            $branch = Branch::query()->whereKey($branchId)->first();

            if ($branch && $this->canAccess($user, $branch)) {
                return $branch;
            }

            session()->forget(self::SESSION_KEY);
        }

        $available = $this->availableFor($user);
        $default = $available->first(function (Branch $branch) use ($user) {
            return $user->isOwner() || (bool) optional($branch->pivot)->is_default;
        });

        $branch = $default ?: ($available->count() === 1 ? $available->first() : null);

        if ($branch) {
            $this->set($branch);
        }

        return $branch;
    }

    public function set(Branch $branch): void
    {
        session()->put(self::SESSION_KEY, $branch->getKey());
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
