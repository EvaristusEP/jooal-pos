<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportBranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private const REPORT_DATE = '2026-06-15';

    public function test_owner_default_report_aggregates_all_mapped_branches_and_separates_unmapped_transactions(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [$firstBranch, $secondBranch, $menu] = $this->reportContext();
        $first = $this->createTransaction('TX-JKT-001', $firstBranch, 'cash', 'dine_in', 10000, 2, $menu);
        $second = $this->createTransaction('TX-SBY-001', $secondBranch, 'qris', 'take_away', 20000, 3, $menu);
        $unmapped = $this->createTransaction('TX-OLD-001', null, 'cash', 'take_away', 5000, 9, $menu);

        $response = $this->actingAs($owner)->get('/reports?date='.self::REPORT_DATE);

        $response->assertOk()
            ->assertSee('Semua Cabang')
            ->assertSee($first->transaction_number)
            ->assertSee($second->transaction_number)
            ->assertDontSee($unmapped->transaction_number)
            ->assertSee('1 transaksi belum dipetakan ke cabang.');

        $response->assertViewHas('totalTransactions', 2)
            ->assertViewHas('totalSales', 30000.0)
            ->assertViewHas('totalItemsSold', 5)
            ->assertViewHas('unmappedTransactionCount', 1)
            ->assertViewHas('paymentTotals', function (array $totals) {
                return (float) $totals['cash'] === 10000.0 && (float) $totals['qris'] === 20000.0;
            })
            ->assertViewHas('serviceTotals', function (array $totals) {
                return (int) $totals['dine_in'] === 1 && (int) $totals['take_away'] === 1;
            })
            ->assertViewHas('topProducts', function ($products) {
                return $products->count() === 1 && (int) $products->first()->quantity_sold === 5;
            })
            ->assertViewHas('transactions', function ($transactions) {
                return $transactions->count() === 2;
            });
    }

    public function test_owner_can_filter_every_report_aggregate_to_one_active_branch(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [$firstBranch, $secondBranch, $menu] = $this->reportContext();
        $first = $this->createTransaction('TX-JKT-002', $firstBranch, 'cash', 'dine_in', 10000, 2, $menu);
        $second = $this->createTransaction('TX-SBY-002', $secondBranch, 'qris', 'take_away', 20000, 3, $menu);

        $response = $this->actingAs($owner)->get('/reports?date='.self::REPORT_DATE.'&branch_id='.$secondBranch->id);

        $response->assertOk()
            ->assertSee($second->transaction_number)
            ->assertDontSee($first->transaction_number)
            ->assertViewHas('selectedBranchId', $secondBranch->id)
            ->assertViewHas('totalTransactions', 1)
            ->assertViewHas('totalSales', 20000.0)
            ->assertViewHas('totalItemsSold', 3)
            ->assertViewHas('paymentTotals', function (array $totals) {
                return ! isset($totals['cash']) && (float) $totals['qris'] === 20000.0;
            })
            ->assertViewHas('serviceTotals', function (array $totals) {
                return ! isset($totals['dine_in']) && (int) $totals['take_away'] === 1;
            })
            ->assertViewHas('topProducts', function ($products) {
                return $products->count() === 1 && (int) $products->first()->quantity_sold === 3;
            })
            ->assertViewHas('transactions', function ($transactions) {
                return $transactions->count() === 1;
            });
    }

    public function test_manager_report_is_fixed_to_assigned_active_branch_even_if_request_selects_another(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        [$firstBranch, $secondBranch, $menu] = $this->reportContext();
        $manager->branches()->attach($firstBranch, ['is_default' => true, 'is_active' => true]);
        $first = $this->createTransaction('TX-JKT-003', $firstBranch, 'cash', 'dine_in', 10000, 2, $menu);
        $second = $this->createTransaction('TX-SBY-003', $secondBranch, 'qris', 'take_away', 20000, 3, $menu);

        $this->actingAs($manager)
            ->withSession(['active_branch_id' => $firstBranch->id])
            ->get('/reports?date='.self::REPORT_DATE.'&branch_id='.$secondBranch->id)
            ->assertForbidden();

        $this->withSession(['active_branch_id' => $firstBranch->id])
            ->get('/reports?date='.self::REPORT_DATE)
            ->assertOk()
            ->assertSee($first->transaction_number)
            ->assertDontSee($second->transaction_number)
            ->assertViewHas('totalTransactions', 1)
            ->assertViewHas('totalSales', 10000.0)
            ->assertViewHas('totalItemsSold', 2)
            ->assertViewHas('transactions', function ($transactions) {
                return $transactions->count() === 1;
            });
    }

    public function test_owner_all_branches_includes_inactive_branch_refunds_once_and_excludes_mismatched_unmapped_refund(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [$firstBranch, $inactiveBranch, $menu] = $this->reportContext();
        $inactiveBranch->is_active = false;
        $inactiveBranch->save();

        $first = $this->createTransaction('TX-REFUND-A', $firstBranch, 'cash', 'dine_in', 10000, 4, $menu);
        $firstItem = $first->items()->firstOrFail();
        $firstItem->update(['quantity' => 2, 'unit_price' => 25, 'line_total' => 5000]);
        $secondItem = TransactionItem::create([
            'transaction_id' => $first->id, 'menu_id' => $menu->id, 'menu_name' => $menu->name,
            'quantity' => 2, 'unit_price' => 25, 'menu_price' => $menu->price, 'branch_price' => $menu->price, 'line_total' => 5000,
        ]);
        $inactive = $this->createTransaction('TX-REFUND-INACTIVE', $inactiveBranch, 'qris', 'take_away', 20000, 2, $menu);
        $unmapped = $this->createTransaction('TX-REFUND-NULL', null, 'cash', 'take_away', 5000, 1, $menu);

        $recorded = $this->createRefund($first, $firstBranch, $owner, 'RF-MULTI-LINE', 5000, 'recorded', self::REPORT_DATE.' 13:00:00', [
            [$firstItem, 1, 2500], [$secondItem, 1, 2500],
        ]);
        $alreadyConfirmed = $this->createRefund($first, $firstBranch, $owner, 'RF-CONFIRMED', 2500, 'manually_confirmed', self::REPORT_DATE.' 14:00:00', [
            [$firstItem, 1, 2500],
        ]);
        $this->createRefund($inactive, $inactiveBranch, $owner, 'RF-INACTIVE-BRANCH', 10000, 'recorded', self::REPORT_DATE.' 15:00:00', [
            [$inactive->items()->firstOrFail(), 1, 10000],
        ]);
        $this->createRefund($unmapped, $firstBranch, $owner, 'RF-MISMATCHED-BRANCH', 500, 'recorded', self::REPORT_DATE.' 16:00:00', [
            [$unmapped->items()->firstOrFail(), 1, 500],
        ]);

        $allResponse = $this->actingAs($owner)->get('/reports?date='.self::REPORT_DATE)->assertOk()
            ->assertSee('Penjualan Bruto')
            ->assertSee('Total Refund')
            ->assertSee('Penjualan Bersih')
            ->assertSee('Dicatat manual', false)
            ->assertSee('tanggal refund dicatat', false);
        $allResponse->assertViewHas('totalSales', 30000.0)
            ->assertViewHas('grossSales', 30000.0)
            ->assertViewHas('totalRefunds', 17500.0)
            ->assertViewHas('netSales', 12500.0)
            ->assertViewHas('unmappedTransactionCount', 1);

        $this->get('/reports?date='.self::REPORT_DATE.'&branch_id='.$firstBranch->id)
            ->assertOk()
            ->assertViewHas('grossSales', 10000.0)
            ->assertViewHas('totalRefunds', 7500.0)
            ->assertViewHas('netSales', 2500.0)
            ->assertViewHas('transactions', function ($transactions) use ($first, $inactive, $unmapped) {
                return $transactions->count() === 1
                    && $transactions->first()->id === $first->id
                    && ! $transactions->contains('id', $inactive->id)
                    && ! $transactions->contains('id', $unmapped->id);
            });

        $beforeConfirmation = $this->get('/reports?date='.self::REPORT_DATE)->viewData('totalRefunds');
        $this->patchJson('/transactions/'.$first->id.'/refunds/'.$recorded->id.'/confirm-manual')->assertOk();
        $afterConfirmation = $this->get('/reports?date='.self::REPORT_DATE)->assertOk()->viewData('totalRefunds');
        $this->assertSame($beforeConfirmation, $afterConfirmation);
        $this->assertSame('manually_confirmed', $alreadyConfirmed->fresh()->status);
        $this->assertSame(3, RefundItem::whereIn('refund_id', [$recorded->id, $alreadyConfirmed->id])->count());
    }

    public function test_refund_period_uses_recorded_at_while_gross_sales_uses_original_sale_date(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        [$branch, , $menu] = $this->reportContext();
        $transaction = $this->createTransaction('TX-REFUND-DATE', $branch, 'cash', 'take_away', 10000, 2, $menu);
        $this->createRefund($transaction, $branch, $owner, 'RF-NEXT-DAY', 5000, 'recorded', '2026-06-16 09:30:00', [
            [$transaction->items()->firstOrFail(), 1, 5000],
        ]);

        $this->actingAs($owner)->get('/reports?date=2026-06-15')->assertOk()
            ->assertViewHas('grossSales', 10000.0)
            ->assertViewHas('totalRefunds', 0.0)
            ->assertViewHas('netSales', 10000.0);
        $this->get('/reports?date=2026-06-16')->assertOk()
            ->assertViewHas('grossSales', 0.0)
            ->assertViewHas('totalRefunds', 5000.0)
            ->assertViewHas('netSales', -5000.0);
    }

    public function test_manager_report_refund_aggregate_is_limited_to_assigned_active_branch(): void
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        [$firstBranch, $secondBranch, $menu] = $this->reportContext();
        $manager->branches()->attach($firstBranch, ['is_default' => true, 'is_active' => true]);
        $first = $this->createTransaction('TX-MANAGER-REFUND-A', $firstBranch, 'cash', 'dine_in', 10000, 2, $menu);
        $second = $this->createTransaction('TX-MANAGER-REFUND-B', $secondBranch, 'qris', 'take_away', 20000, 2, $menu);
        $this->createRefund($first, $firstBranch, $manager, 'RF-MANAGER-A', 5000, 'recorded', self::REPORT_DATE.' 10:00:00', [
            [$first->items()->firstOrFail(), 1, 5000],
        ]);
        $this->createRefund($second, $secondBranch, $manager, 'RF-MANAGER-B', 10000, 'recorded', self::REPORT_DATE.' 11:00:00', [
            [$second->items()->firstOrFail(), 1, 10000],
        ]);

        $this->actingAs($manager)->withSession(['active_branch_id' => $firstBranch->id])
            ->get('/reports?date='.self::REPORT_DATE)
            ->assertOk()
            ->assertViewHas('grossSales', 10000.0)
            ->assertViewHas('totalRefunds', 5000.0)
            ->assertViewHas('netSales', 5000.0)
            ->assertViewHas('transactions', function ($transactions) use ($first, $second) {
                return $transactions->count() === 1 && $transactions->first()->id === $first->id && ! $transactions->contains('id', $second->id);
            });
    }

    private function reportContext(): array
    {
        $firstBranch = $this->createBranch('JKT-01', 'JOOAL Jakarta');
        $secondBranch = $this->createBranch('SBY-01', 'JOOAL Surabaya');
        $category = Category::create(['name' => 'Coffee', 'slug' => 'coffee-report', 'sort_order' => 1, 'is_active' => true]);
        $menu = Menu::create(['category_id' => $category->id, 'name' => 'Kopi Susu', 'slug' => 'kopi-susu-report', 'price' => 10000, 'is_active' => true]);

        return [$firstBranch, $secondBranch, $menu];
    }

    private function createBranch(string $code, string $name): Branch
    {
        return Branch::create(['code' => $code, 'name' => $name, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
    }

    private function createTransaction(string $number, ?Branch $branch, string $paymentMethod, string $serviceType, int $total, int $quantity, Menu $menu): Transaction
    {
        $transaction = Transaction::create([
            'transaction_number' => $number,
            'cashier_id' => null,
            'branch_id' => $branch ? $branch->id : null,
            'customer_name' => 'Report Test',
            'service_type' => $serviceType,
            'fulfillment_type' => 'self_service',
            'subtotal' => $total,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $total,
            'amount_paid' => $total,
            'change_amount' => 0,
            'payment_method' => $paymentMethod,
            'status' => 'completed',
            'sold_at' => self::REPORT_DATE.' 12:00:00',
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'quantity' => $quantity,
            'unit_price' => $total / $quantity,
            'menu_price' => $menu->price,
            'branch_price' => $menu->price,
            'line_total' => $total,
        ]);

        return $transaction;
    }

    private function createRefund(Transaction $transaction, Branch $branch, User $actor, string $number, int $amount, string $status, string $recordedAt, array $items): Refund
    {
        $refund = Refund::create([
            'refund_number' => $number,
            'transaction_id' => $transaction->id,
            'branch_id' => $branch->id,
            'recorded_by' => $actor->id,
            'confirmed_by' => $status === 'manually_confirmed' ? $actor->id : null,
            'refund_type' => 'partial',
            'amount' => $amount,
            'status' => $status,
            'manual_method' => 'cash',
            'reason' => 'Test refund',
            'idempotency_key' => 'KEY-'.$number,
            'request_fingerprint' => str_repeat('a', 64),
            'recorded_at' => $recordedAt,
            'manually_confirmed_at' => $status === 'manually_confirmed' ? $recordedAt : null,
        ]);

        foreach ($items as [$transactionItem, $quantity, $itemAmount]) {
            RefundItem::create([
                'refund_id' => $refund->id,
                'transaction_item_id' => $transactionItem->id,
                'quantity' => $quantity,
                'amount' => $itemAmount,
            ]);
        }

        return $refund;
    }
}
