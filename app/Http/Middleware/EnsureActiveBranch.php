<?php

namespace App\Http\Middleware;

use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveBranch
{
    public function handle(Request $request, Closure $next)
    {
        $branchContext = app(BranchContext::class);
        $branch = $branchContext->activeFor($request->user());

        if (! $branch) {
            if ($request->user()->isOwner()) {
                return $next($request);
            }

            return redirect()
                ->route('branches.select')
                ->withErrors(['branch' => 'Akun Anda belum ditugaskan ke cabang aktif. Hubungi Owner sebelum menggunakan POS.']);
        }

        $request->attributes->set('active_branch', $branch);

        return $next($request);
    }
}
