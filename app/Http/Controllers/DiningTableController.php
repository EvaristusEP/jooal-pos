<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\DiningTable;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    public function index(Request $request, BranchContext $context): View
    {
        $branch = $this->branchFor($request, $context, false);
        $scopeAll = $request->user()->isOwner() && $request->query('branch_id') === 'all';
        $branches = $request->user()->isOwner()
            ? Branch::query()->orderBy('name')->get()
            : collect([$branch])->filter();
        $tables = DiningTable::query()->with('branch')->when($branch && ! $scopeAll, fn ($q) => $q->where('branch_id', $branch->id))
            ->orderBy('branch_id')->orderBy('name')->get();
        return view('tables.index', compact('tables', 'branches', 'branch', 'scopeAll'));
    }

    public function store(Request $request, BranchContext $context)
    {
        $data = $request->validate([
            'branch_id' => [$request->user()->isOwner() ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:80'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ], ['name.required' => 'Nama meja wajib diisi.', 'capacity.min' => 'Kapasitas meja minimal 1 orang.']);
        $branch = $this->branchFor($request, $context, true);
        $branchId = $branch ? $branch->id : $data['branch_id'];
        $this->assertBranch($request, $context, (int) $branchId);
        $request->validate(['name' => [Rule::unique('dining_tables')->where('branch_id', $branchId)]]);
        DiningTable::create(['branch_id' => $branchId, 'name' => trim($data['name']), 'capacity' => $data['capacity'], 'is_active' => true]);
        return redirect()->route('tables.index', $request->user()->isOwner() ? ['branch_id' => $branchId] : [])->with('success', 'Meja berhasil ditambahkan.');
    }

    public function update(Request $request, DiningTable $table, BranchContext $context)
    {
        $this->authorizeTable($request, $context, $table);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('dining_tables')->where('branch_id', $table->branch_id)->ignore($table->id)],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
        ], ['name.required' => 'Nama meja wajib diisi.', 'capacity.min' => 'Kapasitas meja minimal 1 orang.']);
        $table->update(['name' => trim($data['name']), 'capacity' => $data['capacity']]);
        return redirect()->route('tables.index', ['branch_id' => $table->branch_id])->with('success', 'Meja berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, DiningTable $table, BranchContext $context)
    {
        $this->authorizeTable($request, $context, $table);
        $table->update(['is_active' => ! $table->is_active]);
        return redirect()->route('tables.index', ['branch_id' => $table->branch_id])->with('success', 'Status meja diperbarui.');
    }

    private function branchFor(Request $request, BranchContext $context, bool $required): ?Branch
    {
        if (! $request->user()->isOwner()) {
            $branch = $context->activeFor($request->user());
            abort_unless($branch && $context->canAccess($request->user(), $branch), 403, 'Cabang aktif yang ditugaskan wajib dipilih.');
            return $branch;
        }
        $id = $request->input('branch_id', $request->query('branch_id'));
        if ((! $id || $id === 'all') && ! $required) return $context->activeFor($request->user());
        $branch = Branch::query()->findOrFail($id);
        abort_unless($branch->is_active || $request->user()->isOwner(), 403);
        if ($branch->is_active && (string) $request->query('branch_id') === (string) $branch->id) $context->set($branch);
        return $branch;
    }

    private function assertBranch(Request $request, BranchContext $context, int $id): void
    {
        $branch = Branch::query()->findOrFail($id);
        abort_unless($request->user()->isOwner() || $context->canAccess($request->user(), $branch), 403, 'Akses ke cabang tersebut ditolak.');
    }

    private function authorizeTable(Request $request, BranchContext $context, DiningTable $table): void
    {
        $branch = $table->branch;
        if (! $request->user()->isOwner()) {
            $active = $context->activeFor($request->user());
            abort_unless($active && (int) $active->id === (int) $table->branch_id && $context->canAccess($request->user(), $branch), 403);
        }
    }
}
