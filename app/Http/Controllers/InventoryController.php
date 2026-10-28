<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Models\RecipeItem;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderInventoryPage($request, 'branch-stocks', 'inventory.stocks.index');
    }

    public function stockInPage(Request $request)
    {
        return $this->renderInventoryPage($request, 'stock-in', 'inventory.stock-in.index');
    }

    public function branchStocks(Request $request)
    {
        return $this->renderInventoryPage($request, 'branch-stocks', 'inventory.stocks.index');
    }

    public function movementHistory(Request $request)
    {
        return $this->renderInventoryPage($request, 'movements', 'inventory.movements.index');
    }

    public function materials(Request $request)
    {
        abort_unless($request->user()->isOwner(), 403);
        return $this->renderInventoryPage($request, 'materials', 'inventory.materials.index');
    }

    private function renderInventoryPage(Request $request, string $page, string $pageRoute)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ], [
            'date_from.date_format' => 'Tanggal awal tidak valid.',
            'date_to.date_format' => 'Tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);
        $selectedBranch = $this->selectedBranch($request);
        if (! $request->user()->isOwner() && ! $selectedBranch) {
            return redirect()->route('branches.select')->withErrors([
                'branch' => 'Pilih cabang yang ditugaskan sebelum membuka inventaris.',
            ]);
        }

        $branches = $request->user()->isOwner()
            ? Branch::query()->orderBy('name')->get()
            : collect([$selectedBranch]);
        $displayBranches = $selectedBranch ? collect([$selectedBranch]) : $branches;
        $ingredients = Ingredient::query()->orderBy('name')->get();
        $branchIds = $branches->pluck('id');
        $stocks = BranchStock::query()
            ->with(['branch', 'ingredient'])
            ->whereIn('branch_id', $branchIds)
            ->get()
            ->keyBy(fn (BranchStock $stock) => $stock->branch_id.'-'.$stock->ingredient_id);

        $stockRows = collect();
        foreach ($displayBranches as $branch) {
            foreach ($ingredients as $ingredient) {
                $stockRows->push([
                    'branch' => $branch,
                    'ingredient' => $ingredient,
                    'stock' => $stocks->get($branch->id.'-'.$ingredient->id),
                    'quantity' => (float) optional($stocks->get($branch->id.'-'.$ingredient->id))->quantity,
                    'is_low' => (float) optional($stocks->get($branch->id.'-'.$ingredient->id))->quantity <= (float) $ingredient->minimum_stock,
                ]);
            }
        }

        $movementsQuery = StockMovement::query()
            ->with(['branch', 'ingredient', 'user', 'stockReceiptItem.receipt.supplier'])
            ->whereIn('branch_id', $branchIds)
            ->latest();
        if ($selectedBranch) {
            $movementsQuery->where('branch_id', $selectedBranch->id);
        }
        if (! empty($filters['date_from'])) {
            $movementsQuery->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $movementsQuery->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $receiptsQuery = StockReceipt::query()
            ->with(['branch', 'supplier', 'receiver', 'items.ingredient'])
            ->whereIn('branch_id', $branchIds)
            ->latest('received_at');
        if ($selectedBranch) {
            $receiptsQuery->where('branch_id', $selectedBranch->id);
        }
        if (! empty($filters['date_from'])) {
            $receiptsQuery->where('received_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $receiptsQuery->where('received_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return view('dashboard.inventory', [
            'branches' => $branches,
            'displayBranches' => $displayBranches,
            'selectedBranch' => $selectedBranch,
            'filters' => $filters,
            'page' => $page,
            'pageRoute' => $pageRoute,
            'ingredients' => $ingredients,
            'stockRows' => $stockRows,
            'movements' => $movementsQuery->limit(50)->get(),
            'receipts' => $receiptsQuery->limit(50)->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'stockInSubmissionKey' => (string) Str::uuid(),
            'lowStockCount' => $stockRows->where('is_low', true)->where('ingredient.is_active', true)->count(),
        ]);
    }

    public function storeIngredient(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('ingredients', 'slug')],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
        ]);
        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);
        $validated['is_active'] = true;

        Ingredient::create($validated);

        return redirect()->route('inventory.materials.index')->with('success', 'Bahan baku berhasil ditambahkan.');
    }

    public function editIngredient(Ingredient $ingredient)
    {
        return view('inventory.ingredients.edit', compact('ingredient'));
    }

    public function updateIngredient(Request $request, Ingredient $ingredient): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', Rule::unique('ingredients', 'slug')->ignore($ingredient->id)],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'name.required' => 'Nama bahan wajib diisi.',
            'unit.required' => 'Satuan bahan wajib diisi.',
            'minimum_stock.required' => 'Stok minimum wajib diisi.',
            'minimum_stock.numeric' => 'Stok minimum harus berupa angka.',
            'minimum_stock.min' => 'Stok minimum tidak boleh negatif.',
        ]);

        $newUnit = trim($validated['unit']);
        if (strtolower($newUnit) !== strtolower((string) $ingredient->unit)
            && ($ingredient->recipeItems()->exists() || $ingredient->stocks()->exists() || $ingredient->movements()->exists())) {
            throw ValidationException::withMessages([
                'unit' => 'Satuan dasar tidak dapat diubah setelah ada resep atau histori stok karena akan mengubah arti saldo dan ledger.',
            ]);
        }

        $validated['slug'] = Str::slug($validated['slug'] ?? $validated['name']);
        $validated['unit'] = $newUnit;
        $ingredient->update($validated);

        return redirect()->route('inventory.materials.index')->with('success', 'Data bahan baku berhasil diperbarui.');
    }

    public function toggleIngredient(Ingredient $ingredient): RedirectResponse
    {
        $ingredient->update(['is_active' => ! $ingredient->is_active]);

        return redirect()->route('inventory.materials.index')->with('success', $ingredient->is_active
            ? 'Bahan baku berhasil diaktifkan.'
            : 'Bahan baku berhasil dinonaktifkan.');
    }

    public function stockIn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'submission_key' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('ingredients', 'id')->where('is_active', true)],
            'items.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999.999',
                function ($attribute, $value, $fail) {
                    if (! preg_match('/^\d+(?:\.\d+)?$/', (string) $value)) {
                        $fail('Kuantitas harus berupa angka desimal biasa.');
                        return;
                    }

                    $decimal = explode('.', (string) $value, 2)[1] ?? '';
                    if (strlen($decimal) > 3) {
                        $fail('Kuantitas maksimal memiliki 3 angka desimal.');
                    }
                },
            ],
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'supplier_id.required' => 'Pilih pemasok untuk penerimaan stok.',
            'supplier_id.exists' => 'Pemasok tidak ditemukan atau sudah nonaktif.',
            'items.required' => 'Tambahkan minimal satu bahan pada penerimaan.',
            'items.*.ingredient_id.required' => 'Pilih bahan untuk setiap baris penerimaan.',
            'items.*.ingredient_id.distinct' => 'Bahan yang sama hanya boleh dicantumkan satu kali dalam satu penerimaan.',
            'items.*.ingredient_id.exists' => 'Bahan tidak ditemukan atau sudah nonaktif.',
            'items.*.quantity.required' => 'Jumlah setiap bahan wajib diisi.',
            'items.*.quantity.gt' => 'Jumlah penerimaan harus lebih besar dari nol.',
            'reason.required' => 'Alasan atau referensi penerimaan wajib diisi.',
        ]);

        $branch = $this->writeBranch($request, $validated['branch_id'] ?? null);
        $submissionKey = $validated['submission_key'] ?? (string) Str::uuid();
        $receiptNumber = 'RCV-'.Str::upper(str_replace('-', '', $submissionKey));

        $alreadyRecorded = DB::transaction(function () use ($branch, $validated, $request, $receiptNumber) {
            // Serialize receipts within a branch, including first-time stock-row creation.
            $lockedBranch = Branch::query()->whereKey($branch->id)->where('is_active', true)->lockForUpdate()->first();
            abort_unless($lockedBranch, 422, 'Cabang tidak aktif; penerimaan stok tidak dapat dicatat.');
            if (! $request->user()->isOwner()) {
                abort_unless($request->user()->branches()->whereKey($lockedBranch->id)->wherePivot('is_active', true)->exists(), 403, 'Akses ke cabang tersebut ditolak.');
            }
            $supplier = Supplier::query()->whereKey($validated['supplier_id'])->where('is_active', true)->lockForUpdate()->first();
            abort_unless($supplier, 422, 'Pemasok tidak aktif; pilih pemasok aktif untuk penerimaan baru.');

            $existingReceipt = StockReceipt::query()->with('items')->where('receipt_number', $receiptNumber)->first();
            if ($existingReceipt) {
                $incomingItems = collect($validated['items'])->sortBy('ingredient_id')->values();
                $existingItems = $existingReceipt->items->sortBy('ingredient_id')->values();
                $sameItems = $incomingItems->count() === $existingItems->count()
                    && $incomingItems->zip($existingItems)->every(function ($pair) {
                        [$incoming, $existing] = $pair;
                        return (int) $incoming['ingredient_id'] === (int) $existing->ingredient_id
                            && $this->quantityToThousandths((string) $incoming['quantity']) === $this->quantityToThousandths((string) $existing->quantity);
                    });

                if ((int) $existingReceipt->branch_id !== (int) $branch->id
                    || (int) $existingReceipt->supplier_id !== (int) $supplier->id
                    || $existingReceipt->reason !== $validated['reason']
                    || ! $sameItems) {
                    throw ValidationException::withMessages([
                        'submission_key' => 'Kunci pengiriman sudah digunakan untuk data penerimaan yang berbeda. Muat ulang halaman sebelum mencoba lagi.',
                    ]);
                }

                return true;
            }

            $receipt = StockReceipt::create([
                'receipt_number' => $receiptNumber,
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'received_by' => $request->user()->id,
                'reason' => $validated['reason'],
                'received_at' => Carbon::now(),
            ]);

            $items = collect($validated['items'])->sortBy('ingredient_id');
            foreach ($items as $item) {
                $ingredient = Ingredient::query()->whereKey($item['ingredient_id'])->where('is_active', true)->firstOrFail();
                $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->lockForUpdate()->first();
                if (! $stock) {
                    $stock = BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 0]);
                }

                // Keep DECIMAL(12,3) values exact and use the same normalized
                // decimal string for the balance, movement, and receipt item.
                $quantityUnits = $this->quantityToThousandths((string) $item['quantity']);
                $beforeUnits = $this->quantityToThousandths((string) $stock->quantity);
                $quantity = $this->thousandthsToQuantity($quantityUnits);
                $before = $this->thousandthsToQuantity($beforeUnits);
                $afterUnits = $beforeUnits + $quantityUnits;
                if ($afterUnits > 999999999999) {
                    throw ValidationException::withMessages([
                        'items' => 'Saldo setelah penerimaan melebihi kapasitas DECIMAL(12,3).',
                    ]);
                }
                $after = $this->thousandthsToQuantity($afterUnits);
                $stock->update(['quantity' => $after]);
                $movement = StockMovement::create([
                    'branch_id' => $branch->id,
                    'ingredient_id' => $ingredient->id,
                    'user_id' => $request->user()->id,
                    'type' => 'stock_in',
                    'quantity_delta' => $quantity,
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'reason' => $validated['reason'],
                ]);
                StockReceiptItem::create([
                    'stock_receipt_id' => $receipt->id,
                    'ingredient_id' => $ingredient->id,
                    'stock_movement_id' => $movement->id,
                    'quantity' => $quantity,
                ]);
            }

            return false;
        });

        return redirect()->route('inventory.stock-in.index', ['branch_id' => $branch->id])->with('success', $alreadyRecorded
            ? 'Penerimaan ini sudah tercatat; saldo stok tidak ditambahkan kembali.'
            : 'Dokumen penerimaan dan stok masuk berhasil dicatat.');
    }

    public function receipt(StockReceipt $receipt, Request $request)
    {
        if (! $request->user()->isOwner()) {
            $activeBranch = app(BranchContext::class)->activeFor($request->user());
            abort_unless($activeBranch && (int) $activeBranch->id === (int) $receipt->branch_id, 403);
        }

        $receipt->load(['branch', 'supplier', 'receiver', 'items.ingredient', 'items.movement']);

        return view('inventory.receipts.show', compact('receipt'));
    }

    public function adjust(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->where('is_active', true)],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $branch = $this->writeBranch($request, $validated['branch_id'] ?? null);
        $delta = $validated['direction'] === 'decrease' ? -1 * (float) $validated['quantity'] : (float) $validated['quantity'];
        $this->recordMovement($branch, (int) $validated['ingredient_id'], $delta, 'adjustment', $validated['reason'], $request->user()->id);

        return redirect()->route('inventory.stocks.index', ['branch_id' => $branch->id])->with('success', 'Penyesuaian stok berhasil dicatat.');
    }

    private function selectedBranch(Request $request): ?Branch
    {
        if ($request->user()->isOwner()) {
            if (! $request->filled('branch_id')) {
                return null;
            }
            $branch = Branch::query()->whereKey($request->input('branch_id'))->firstOrFail();
            if ($branch->is_active) app(BranchContext::class)->set($branch);
            return $branch;
        }

        return app(BranchContext::class)->activeFor($request->user());
    }

    private function writeBranch(Request $request, ?int $branchId): Branch
    {
        if ($request->user()->isOwner()) {
            $branch = Branch::query()->whereKey($branchId)->where('is_active', true)->first();
            abort_unless($branch, 422, 'Cabang aktif wajib dipilih.');

            return $branch;
        }

        $branch = app(BranchContext::class)->activeFor($request->user());
        abort_unless($branch && (! $branchId || $branch->id === (int) $branchId), 403, 'Akses ke cabang tersebut ditolak.');

        return $branch;
    }

    private function recordMovement(Branch $branch, int $ingredientId, float $delta, string $type, string $reason, int $userId): void
    {
        DB::transaction(function () use ($branch, $ingredientId, $delta, $type, $reason, $userId) {
            $ingredient = Ingredient::query()->whereKey($ingredientId)->where('is_active', true)->firstOrFail();
            $stock = BranchStock::query()->where('branch_id', $branch->id)->where('ingredient_id', $ingredient->id)->lockForUpdate()->first();
            if (! $stock) {
                $stock = BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 0]);
            }

            $before = (float) $stock->quantity;
            $after = $before + $delta;
            if ($after < 0) {
                abort(422, 'Stok tidak mencukupi. Stok tidak boleh menjadi negatif.');
            }

            $stock->update(['quantity' => $after]);
            StockMovement::create([
                'branch_id' => $branch->id,
                'ingredient_id' => $ingredient->id,
                'user_id' => $userId,
                'type' => $type,
                'quantity_delta' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => $reason,
            ]);
        });
    }

    private function quantityToThousandths(string $quantity): int
    {
        [$whole, $fraction] = array_pad(explode('.', $quantity, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }

    private function thousandthsToQuantity(int $quantity): string
    {
        return intdiv($quantity, 1000).'.'.str_pad((string) ($quantity % 1000), 3, '0', STR_PAD_LEFT);
    }
}
