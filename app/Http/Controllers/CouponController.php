<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::query()->withCount('transactions')->orderByDesc('created_at')->get();

        return view('coupons.index', compact('coupons'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = true;
        Coupon::create($data);

        return redirect()->route('coupons.index')->with('success', 'Kupon berhasil dibuat.');
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        DB::transaction(function () use ($request, $coupon) {
            $lockedCoupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            $lockedCoupon->update($this->validated($request, $lockedCoupon));
        }, 3);

        return redirect()->route('coupons.index')->with('success', 'Kupon berhasil diperbarui.');
    }

    public function toggleStatus(Coupon $coupon): RedirectResponse
    {
        DB::transaction(function () use ($coupon) {
            $lockedCoupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            $lockedCoupon->update(['is_active' => ! $lockedCoupon->is_active]);
        }, 3);

        return redirect()->route('coupons.index')->with('success', 'Status kupon diperbarui.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($coupon) {
            $lockedCoupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedCoupon->used_count > 0 || $lockedCoupon->transactions()->exists()) {
                return false;
            }

            $lockedCoupon->delete();

            return true;
        }, 3);

        if (! $deleted) {
            return redirect()->route('coupons.index')->withErrors([
                'coupon' => 'Kupon yang pernah dipakai tidak dapat dihapus agar histori transaksi tetap utuh. Nonaktifkan kupon sebagai gantinya.',
            ]);
        }

        return redirect()->route('coupons.index')->with('success', 'Kupon yang belum pernah dipakai berhasil dihapus.');
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        // Normalize before the unique check so validation matches the case-insensitive
        // code comparison used by MySQL's typical collations.
        if (is_string($request->input('code'))) {
            $request->merge(['code' => Str::upper(trim($request->input('code')))]);
        }

        $codeRule = Rule::unique('coupons', 'code');
        if ($coupon) {
            $codeRule = $codeRule->ignore($coupon->getKey());
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', $codeRule],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'regex:/^\d+(?:\.\d{1,2})?$/', 'gt:0', 'max:99999999.99'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
        ], [
            'code.regex' => 'Kode kupon hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'ends_at.after_or_equal' => 'Tanggal berakhir harus sama atau setelah tanggal mulai.',
        ]);

        if ($data['discount_type'] === 'percentage' && (float) $data['discount_value'] > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_value' => 'Diskon persentase tidak boleh melebihi 100%.',
            ]);
        }

        $recordedUses = $coupon ? $coupon->transactions()->count() : 0;
        $usesToPreserve = max((int) optional($coupon)->used_count, (int) $recordedUses);
        if ($coupon && isset($data['usage_limit']) && (int) $data['usage_limit'] < $usesToPreserve) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'usage_limit' => 'Batas penggunaan tidak boleh lebih rendah dari jumlah penggunaan atau transaksi yang sudah tercatat.',
            ]);
        }

        return $data;
    }
}
