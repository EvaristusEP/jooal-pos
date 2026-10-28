<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Refund;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundHistoryReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_and_receipt_show_separate_refund_records_without_overwriting_original_invoice(): void
    {
        [$branch, $transaction, $latte, $cake] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $recorded = $this->actingAs($owner)->postJson($this->refundEndpoint($transaction), $this->payload($latte, 'history-recorded', 'Catatan refund pertama'))->assertCreated();
        $confirmed = $this->postJson($this->refundEndpoint($transaction), $this->payload($cake, 'history-confirmed', 'Catatan refund kedua'))->assertCreated();
        $refundToConfirm = Refund::findOrFail($confirmed->json('data.id'));

        $this->from('/history/'.$transaction->id)
            ->post(route('transactions.refunds.confirm-manual', [$transaction, $refundToConfirm]), ['_method' => 'PATCH'])
            ->assertRedirect('/history/'.$transaction->id)
            ->assertSessionHas('status');

        $history = $this->get('/history/'.$transaction->id)->assertOk()
            ->assertSee('Catatan refund')
            ->assertSee($recorded->json('data.refund_number'))
            ->assertSee($refundToConfirm->refund_number)
            ->assertSee('Dicatat — belum terverifikasi')
            ->assertSee('Dikonfirmasi manual — tidak diverifikasi sistem')
            ->assertSee('Dikonfirmasi manual')
            ->assertSee('Catatan refund pertama')
            ->assertSee('Catatan refund kedua')
            ->assertSee('Rp 18.000')
            ->assertSee('Rp 0');

        $this->get('/history')->assertOk()
            ->assertSee('Memiliki refund')
            ->assertSee('Refund tercatat Rp 18.000')
            ->assertSee('Detail &amp; Refund', false);

        $receipt = $this->get('/receipt/'.$transaction->id)->assertOk()
            ->assertSee('Invoice Number')
            ->assertSee($transaction->transaction_number)
            ->assertSee('QRIS')
            ->assertSee('Total Payment')
            ->assertSee('Refund transaksi')
            ->assertSee('Tercatat di POS berarti refund dicatat, bukan bukti')
            ->assertSee('Sistem tidak memverifikasi pembayaran secara independen');

        $this->assertSame('18000.00', $transaction->fresh()->grand_total);
        $this->assertSame('qris', $transaction->fresh()->payment_method);
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(2, $transaction->items()->count());
        $this->assertSame(2, Refund::where('transaction_id', $transaction->id)->count());
        $this->assertSame(1800000, $this->refundCents($transaction));
    }

    public function test_owner_can_view_history_and_receipt_refunds_for_inactive_branch(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $refund = $this->actingAs($owner)->postJson($this->refundEndpoint($transaction), $this->payload($item, 'inactive-history', 'Cabang ditutup'))->assertCreated();

        $branch->is_active = false;
        $branch->save();

        $this->get('/history/'.$transaction->id)->assertOk()
            ->assertSee($refund->json('data.refund_number'))
            ->assertSee('Cabang ditutup');
        $this->get('/receipt/'.$transaction->id)->assertOk()
            ->assertSee($refund->json('data.refund_number'))
            ->assertSee('Cabang ditutup');
    }

    public function test_manager_is_scoped_to_assigned_active_branch_for_history_receipt_and_refund_actions(): void
    {
        [, $transaction, $item] = $this->sale();
        [$otherBranch, $otherTransaction, $otherItem] = $this->sale('JOOAL-002', 'TX-OTHER');
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $manager->branches()->attach($otherBranch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($manager)
            ->withSession(['active_branch_id' => $otherBranch->id])
            ->postJson($this->refundEndpoint($otherTransaction), $this->payload($otherItem, 'manager-owned', 'Branch two'))->assertCreated();

        $manager->branches()->detach($otherBranch);
        $manager->branches()->attach($otherBranch, ['is_default' => false, 'is_active' => false]);
        $otherBranch->is_active = false;
        $otherBranch->save();
        $manager->branches()->attach($transaction->branch_id, ['is_default' => true, 'is_active' => true]);

        $this->withSession(['active_branch_id' => $transaction->branch_id])
            ->get('/history/'.$otherTransaction->id)->assertNotFound();
        $this->get('/receipt/'.$otherTransaction->id)->assertNotFound();
        $this->getJson($this->refundEndpoint($otherTransaction))->assertForbidden();
        $this->patchJson($this->refundEndpoint($otherTransaction).'/'.$otherTransaction->refunds()->first()->id.'/confirm-manual')
            ->assertForbidden();
    }

    public function test_cashier_sees_no_refund_details_and_cannot_confirm_refund(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $refund = $this->actingAs($owner)->postJson($this->refundEndpoint($transaction), $this->payload($item, 'cashier-hidden', 'Private refund reason'))->assertCreated();
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $cashier->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        $this->actingAs($cashier)->withSession(['active_branch_id' => $branch->id])
            ->get('/history/'.$transaction->id)->assertOk()->assertDontSee('Private refund reason')->assertDontSee($refund->json('data.refund_number'))->assertDontSee('id="refund-create-form"', false);
        $this->get('/receipt/'.$transaction->id)->assertOk()->assertDontSee('Private refund reason')->assertDontSee($refund->json('data.refund_number'));
        $this->patchJson($this->refundEndpoint($transaction).'/'.$refund->json('data.id').'/confirm-manual')->assertForbidden();
    }

    public function test_transactions_without_refunds_continue_to_render_history_and_original_receipt(): void
    {
        [$branch, $transaction] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/history/'.$transaction->id)->assertOk()
            ->assertSee($transaction->transaction_number)
            ->assertSee('Catatan refund')
            ->assertSee('Catat refund')
            ->assertSee('Refund penuh')
            ->assertSee('Refund sebagian')
            ->assertSee('name="manual_method"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('data-remaining-cents="1800000"', false)
            ->assertSee('Belum ada refund untuk transaksi ini.');
        $this->get('/receipt/'.$transaction->id)->assertOk()
            ->assertSee($transaction->transaction_number)
            ->assertDontSee('Refund transaksi');
    }

    public function test_owner_history_filter_shows_actual_branch_and_item_details(): void
    {
        [$branchOne, $transactionOne, $itemOne] = $this->sale('JOOAL-001', 'TX-HISTORY-ONE');
        [$branchTwo, $transactionTwo, $itemTwo] = $this->sale('JOOAL-002', 'TX-HISTORY-TWO');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/history?branch_id='.$branchTwo->id)
            ->assertOk()
            ->assertSee($transactionTwo->transaction_number)
            ->assertSee($branchTwo->display_name)
            ->assertSee($itemTwo->menu_name)
            ->assertDontSee($transactionOne->transaction_number);
    }

    public function test_history_refund_form_posts_partial_request_and_shows_server_result(): void
    {
        [, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->get('/history/'.$transaction->id)->assertOk()
            ->assertSee('Catat refund')
            ->assertSee('Estimasi hanya untuk referensi')
            ->assertSee(route('transactions.refunds.store', $transaction), false);

        $this->postJson($this->refundEndpoint($transaction), [
            'refund_type' => 'partial',
            'manual_method' => 'cash',
            'idempotency_key' => 'ui-refund-invalid',
            'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $created = $this->postJson($this->refundEndpoint($transaction), $this->payload($item, 'ui-refund-submit', 'Dicatat dari UI'))->assertCreated();
        $this->assertSame('12000.00', $created->json('data.amount'));
        $this->get('/history/'.$transaction->id)->assertOk()
            ->assertSee($created->json('data.refund_number'))
            ->assertSee('Dicatat — belum terverifikasi');
    }

    private function sale(string $branchCode = 'JOOAL-001', string $number = 'TX-REFUND-UI'): array
    {
        $branch = Branch::create(['code' => $branchCode, 'name' => $branchCode, 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $transaction = Transaction::create([
            'transaction_number' => $number,
            'cashier_id' => null,
            'branch_id' => $branch->id,
            'customer_name' => 'Receipt buyer',
            'service_type' => 'take_away',
            'fulfillment_type' => 'self_service',
            'subtotal' => 18000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 18000,
            'amount_paid' => 18000,
            'change_amount' => 0,
            'payment_method' => 'qris',
            'status' => 'completed',
            'sold_at' => now(),
        ]);
        $latte = $this->item($transaction, 'Latte', 12000);
        $cake = $this->item($transaction, 'Coffee cake', 6000);

        return [$branch, $transaction, $latte, $cake];
    }

    private function item(Transaction $transaction, string $name, int $amount): TransactionItem
    {
        return TransactionItem::create([
            'transaction_id' => $transaction->id,
            'menu_id' => null,
            'menu_name' => $name,
            'quantity' => 1,
            'unit_price' => $amount,
            'menu_price' => null,
            'branch_price' => null,
            'line_total' => $amount,
            'item_options' => [],
        ]);
    }

    private function payload(TransactionItem $item, string $key, string $reason): array
    {
        return [
            'refund_type' => 'partial',
            'reason' => $reason,
            'manual_method' => 'cash',
            'idempotency_key' => $key,
            'items' => [['transaction_item_id' => $item->id, 'quantity' => 1]],
        ];
    }

    private function refundEndpoint(Transaction $transaction): string
    {
        return '/transactions/'.$transaction->id.'/refunds';
    }

    private function refundCents(Transaction $transaction): int
    {
        return (int) round((float) $transaction->refunds()->sum('amount') * 100);
    }
}
