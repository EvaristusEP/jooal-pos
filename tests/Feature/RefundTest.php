<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_assigned_manager_can_create_refund_but_cashier_and_inventory_cannot(): void
    {
        [$branch, $transaction, $item] = $this->sale(100, 2);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $manager = $this->managerFor($branch);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $inventory = User::factory()->create(['role' => User::ROLE_INVENTORY]);

        $this->actingAs($owner)->getJson($this->endpoint($transaction))->assertOk();
        $this->actingAs($manager)->getJson($this->endpoint($transaction))->assertOk();
        $this->actingAs($cashier)->getJson($this->endpoint($transaction))->assertForbidden();
        $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'owner-key'))->assertCreated();
        $this->actingAs($manager)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'manager-key'))->assertCreated();
        $this->actingAs($cashier)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'cashier-key'))->assertForbidden();
        $this->actingAs($inventory)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'inventory-key'))->assertForbidden();
    }

    public function test_manager_cannot_refund_another_branch_and_owner_can_refund_inactive_branch(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $otherBranch = $this->branch('SBY-01', true);
        $manager = $this->managerFor($otherBranch);

        $this->actingAs($manager)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'cross-branch'))->assertForbidden();

        $branch->is_active = false;
        $branch->save();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'inactive-owner'))->assertCreated();
    }

    public function test_null_branch_and_non_completed_transactions_are_rejected(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $transaction->branch_id = null;
        $transaction->save();
        $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'null-branch'))->assertStatus(409);

        $transaction->branch_id = $branch->id;
        $transaction->status = 'pending';
        $transaction->save();
        $this->postJson($this->endpoint($transaction), $this->partialPayload($item, 'pending-sale'))->assertStatus(409);
        $this->assertSame(0, Refund::count());
    }

    public function test_partial_refunds_use_snapshot_value_and_full_refund_covers_remaining_amount(): void
    {
        [$branch, $transaction, $firstItem] = $this->sale(100, 3);
        $secondItem = $this->item($transaction, 50, 1, 'Brownie');
        $transaction->grand_total = '150.00';
        $transaction->subtotal = '150.00';
        $transaction->amount_paid = '150.00';
        $transaction->save();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $first = $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($firstItem, 'partial-1', 1) + ['amount' => '0.01']);
        $first->assertCreated()->assertJsonPath('data.amount', '33.33');

        $secondPayload = $this->partialPayload($firstItem, 'partial-2', 1);
        $this->postJson($this->endpoint($transaction), $secondPayload)->assertCreated()->assertJsonPath('data.amount', '33.33');

        $fullPayload = [
            'refund_type' => 'full', 'reason' => 'Selesaikan sisa refund', 'manual_method' => 'bank_transfer', 'idempotency_key' => 'finish-rest',
        ];
        $this->postJson($this->endpoint($transaction), $fullPayload)->assertCreated()->assertJsonPath('data.amount', '83.34');
        $this->postJson($this->endpoint($transaction), array_merge($fullPayload, ['idempotency_key' => 'over-refund']))->assertStatus(409);

        $this->assertSame(3, Refund::count());
        $this->assertSame(15000, $this->refundAmountCents($transaction));
        $this->assertSame(3, (int) RefundItem::where('transaction_item_id', $firstItem->id)->sum('quantity'));
        $this->assertSame(1, (int) RefundItem::where('transaction_item_id', $secondItem->id)->sum('quantity'));
        $this->assertSame(15000, $this->refundItemAmountCents($transaction));
        $this->assertSame('full', Refund::latest('id')->first()->refund_type);
    }

    public function test_idempotency_returns_same_refund_and_rejects_key_reuse_with_changed_payload(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $payload = $this->partialPayload($item, 'stable-key');

        $first = $this->actingAs($owner)->postJson($this->endpoint($transaction), $payload)->assertCreated();
        $refundId = $first->json('data.id');
        $this->postJson($this->endpoint($transaction), $payload)->assertOk()->assertJsonPath('data.id', $refundId);

        $different = $payload;
        $different['reason'] = 'Alasan berbeda';
        $this->postJson($this->endpoint($transaction), $different)->assertStatus(409)
            ->assertJson(['message' => 'Permintaan refund tidak dapat diproses.']);

        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($otherOwner)->postJson($this->endpoint($transaction), $payload)->assertStatus(409)
            ->assertJson(['message' => 'Permintaan refund tidak dapat diproses.']);
        $this->assertSame(1, Refund::count());
        $this->assertSame(1, RefundItem::count());

        [, $otherTransaction, $otherItem] = $this->sale(100, 1, 'DIFFERENT-SALE');
        $this->postJson($this->endpoint($otherTransaction), $payload)->assertStatus(409)
            ->assertJson(['message' => 'Permintaan refund tidak dapat diproses.']);
        $this->assertSame(1, Refund::count());
    }

    public function test_validation_rejects_missing_reason_invalid_quantity_and_item_from_another_transaction(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        [, $otherTransaction, $otherItem] = $this->sale(200, 1, 'OTHER-TRANSACTION');
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $missingReason = $this->partialPayload($item, 'missing-reason');
        unset($missingReason['reason']);
        $this->actingAs($owner)->postJson($this->endpoint($transaction), $missingReason)->assertUnprocessable();

        $tooMany = $this->partialPayload($item, 'too-many', 2);
        $this->postJson($this->endpoint($transaction), $tooMany)->assertStatus(422);

        $foreignItem = $this->partialPayload($otherItem, 'foreign-item');
        $this->postJson($this->endpoint($transaction), $foreignItem)->assertStatus(422);
        $this->assertSame(0, Refund::count());
    }

    public function test_manual_confirmation_is_recorded_once_without_changing_refund_value(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $created = $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'confirm-me'))->assertCreated();
        $refund = Refund::findOrFail($created->json('data.id'));

        $first = $this->patchJson($this->endpoint($transaction).'/'.$refund->id.'/confirm-manual')->assertOk()
            ->assertJsonPath('data.status', 'manually_confirmed');
        $confirmedAt = $first->json('data.manually_confirmed_at');
        $this->travel(1)->seconds();
        $second = $this->patchJson($this->endpoint($transaction).'/'.$refund->id.'/confirm-manual')->assertOk()
            ->assertJsonPath('data.status', 'manually_confirmed')
            ->assertJsonPath('data.manually_confirmed_at', $confirmedAt);

        $this->assertSame(1, Refund::where('status', 'manually_confirmed')->count());
        $this->assertSame(10000, $this->refundAmountCents($transaction));
    }

    public function test_refund_leaves_original_transaction_stock_and_sale_movements_unchanged(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $ingredient = Ingredient::create(['name' => 'Beans', 'slug' => 'beans-refund', 'unit' => 'gram', 'minimum_stock' => 1, 'is_active' => true]);
        $stock = BranchStock::create(['branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'quantity' => 12]);
        $movement = StockMovement::create([
            'branch_id' => $branch->id, 'ingredient_id' => $ingredient->id, 'user_id' => null,
            'transaction_id' => $transaction->id, 'type' => 'sale', 'quantity_delta' => -1,
            'quantity_before' => 13, 'quantity_after' => 12, 'reason' => 'POS sale',
        ]);
        $original = $transaction->fresh()->only(['grand_total', 'amount_paid', 'payment_method', 'status', 'branch_id']);
        $originalItem = $item->fresh()->only(['transaction_id', 'menu_name', 'quantity', 'unit_price', 'line_total', 'item_options']);
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'no-stock-touch'))->assertCreated();

        $this->assertSame($original, $transaction->fresh()->only(['grand_total', 'amount_paid', 'payment_method', 'status', 'branch_id']));
        $this->assertSame($originalItem, $item->fresh()->only(['transaction_id', 'menu_name', 'quantity', 'unit_price', 'line_total', 'item_options']));
        $this->assertSame('12.000', (string) $stock->fresh()->quantity);
        $this->assertSame(1, StockMovement::where('transaction_id', $transaction->id)->count());
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'branch_id' => $branch->id, 'transaction_id' => $transaction->id]);
        $this->assertSame(1, $transaction->items()->count());
    }

    public function test_detail_insert_failure_rolls_back_refund_header(): void
    {
        [$branch, $transaction, $item] = $this->sale();
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->withoutExceptionHandling();
        RefundItem::creating(function () {
            throw new RuntimeException('simulated refund item insert failure');
        });

        try {
            try {
                $this->actingAs($owner)->postJson($this->endpoint($transaction), $this->partialPayload($item, 'rollback-key'));
                $this->fail('Expected detail persistence failure.');
            } catch (RuntimeException $exception) {
                $this->assertSame('simulated refund item insert failure', $exception->getMessage());
            }
        } finally {
            RefundItem::flushEventListeners();
        }

        $this->assertSame(0, Refund::count());
        $this->assertSame(0, RefundItem::count());
    }

    private function sale(int $lineTotal = 100, int $quantity = 1, string $number = 'REFUND-SALE'): array
    {
        $branch = $this->branch('JKT-'.substr(md5($number), 0, 5), true);
        $transaction = Transaction::create([
            'transaction_number' => $number,
            'cashier_id' => null,
            'branch_id' => $branch->id,
            'customer_name' => 'Refund test',
            'service_type' => 'take_away',
            'fulfillment_type' => 'self_service',
            'subtotal' => $lineTotal,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => $lineTotal,
            'amount_paid' => $lineTotal,
            'change_amount' => 0,
            'payment_method' => 'qris',
            'status' => 'completed',
            'sold_at' => now(),
        ]);
        $item = $this->item($transaction, $lineTotal, $quantity);

        return [$branch, $transaction, $item];
    }

    private function item(Transaction $transaction, int $lineTotal, int $quantity, string $name = 'Latte'): TransactionItem
    {
        return TransactionItem::create([
            'transaction_id' => $transaction->id,
            'menu_id' => null,
            'menu_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $lineTotal / $quantity,
            'menu_price' => null,
            'branch_price' => null,
            'line_total' => $lineTotal,
            'item_options' => [],
        ]);
    }

    private function branch(string $code, bool $active): Branch
    {
        return Branch::create(['code' => $code, 'name' => 'JOOAL '.$code, 'timezone' => 'Asia/Jakarta', 'is_active' => $active]);
    }

    private function managerFor(Branch $branch): User
    {
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER]);
        $manager->branches()->attach($branch, ['is_default' => true, 'is_active' => true]);

        return $manager;
    }

    private function endpoint(Transaction $transaction): string
    {
        return '/transactions/'.$transaction->id.'/refunds';
    }

    private function partialPayload(TransactionItem $item, string $key, int $quantity = 1): array
    {
        return [
            'refund_type' => 'partial',
            'reason' => 'Pesanan dikembalikan customer',
            'manual_method' => 'cash',
            'idempotency_key' => $key,
            'items' => [['transaction_item_id' => $item->id, 'quantity' => $quantity]],
        ];
    }

    private function refundAmountCents(Transaction $transaction): int
    {
        return (int) round((float) Refund::where('transaction_id', $transaction->id)->sum('amount') * 100);
    }

    private function refundItemAmountCents(Transaction $transaction): int
    {
        return (int) round((float) RefundItem::query()->whereIn('refund_id', $transaction->refunds()->select('id'))->sum('amount') * 100);
    }
}
