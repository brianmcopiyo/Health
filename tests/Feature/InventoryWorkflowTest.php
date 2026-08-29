<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStore;
use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Sanctum::actingAs($this->user('pharmacy@riverside.test'));
    }

    public function test_receive_transfer_issue_return_adjust_and_count_keep_a_ledger(): void
    {
        $pharmacy = InventoryStore::query()->where('code', 'PHARM')->firstOrFail();
        $ward = InventoryStore::query()->where('code', 'WARD')->firstOrFail();
        $item = InventoryItem::query()->where('sku', 'PCM-500')->firstOrFail();
        $opening = (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $pharmacy->id)->value('quantity');

        $receipt = $this->postJson('/api/inventory/receipts', [
            'store_id' => $pharmacy->id,
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 20,
                'batch_number' => 'LOT-PCM',
                'expiry_date' => now()->addYear()->toDateString(),
            ]],
        ])->assertCreated();

        $this->assertSame($opening + 20, (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $pharmacy->id)->value('quantity'));

        $this->postJson('/api/inventory/transfers', [
            'from_store_id' => $pharmacy->id,
            'to_store_id' => $ward->id,
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 6,
                'batch_id' => $receipt->json('items.0.batch_id'),
            ]],
        ])->assertCreated();

        $this->assertSame(6.0, (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $ward->id)->value('quantity'));

        $issue = $this->postJson('/api/inventory/issues', [
            'store_id' => $ward->id,
            'kind' => 'ward',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
            ]],
        ])->assertCreated();

        $this->assertSame(4.0, (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $ward->id)->value('quantity'));

        $this->postJson('/api/inventory/returns', [
            'from_store_id' => $ward->id,
            'to_store_id' => $pharmacy->id,
            'issue_id' => $issue->json('id'),
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertCreated();

        $this->assertSame(3.0, (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $ward->id)->value('quantity'));

        $this->postJson('/api/inventory/adjustments', [
            'store_id' => $ward->id,
            'reason' => 'damage',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 1,
                'direction' => 'out',
            ]],
        ])->assertCreated();

        $wardBatch = InventoryBatch::query()->where('item_id', $item->id)->where('store_id', $ward->id)->where('quantity', '>', 0)->first();
        $this->postJson('/api/inventory/counts', [
            'store_id' => $ward->id,
            'items' => [[
                'item_id' => $item->id,
                'batch_id' => $wardBatch?->id,
                'counted_quantity' => 1,
            ]],
        ])->assertCreated();

        $this->assertSame(1.0, (float) InventoryBalance::query()->where('item_id', $item->id)->where('store_id', $ward->id)->value('quantity'));
        $this->assertGreaterThan(5, InventoryMovement::query()->where('item_id', $item->id)->count());

        $this->getJson('/api/inventory/dashboard')->assertOk()
            ->assertJsonStructure(['stock_value', 'items_in_stock', 'low_stock', 'expiring', 'expired', 'recent_receipts', 'recent_movements', 'attention']);
        $this->getJson('/api/inventory/stock')->assertOk();
        $this->getJson('/api/inventory/items/'.$item->id)->assertOk();
    }

    public function test_expired_and_controlled_stock_are_restricted(): void
    {
        $pharmacy = InventoryStore::query()->where('code', 'PHARM')->firstOrFail();
        $item = InventoryItem::query()->where('sku', 'SUP-GLV')->firstOrFail();

        $receipt = $this->postJson('/api/inventory/receipts', [
            'store_id' => $pharmacy->id,
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 4,
                'batch_number' => 'LOT-EXP',
                'expiry_date' => now()->addDay()->toDateString(),
            ]],
        ])->assertCreated();

        $this->patchJson('/api/inventory/batches/'.$receipt->json('items.0.batch_id'), [
            'expiry_date' => now()->subDay()->toDateString(),
            'status' => 'expired',
        ])->assertOk();

        $this->postJson('/api/inventory/issues', [
            'store_id' => $pharmacy->id,
            'items' => [[
                'item_id' => $item->id,
                'batch_id' => $receipt->json('items.0.batch_id'),
                'quantity' => 1,
            ]],
        ])->assertStatus(422);

        Sanctum::actingAs($this->user('nurse@riverside.test'));
        $morphine = InventoryItem::query()->where('sku', 'MOR-10')->firstOrFail();
        $this->postJson('/api/inventory/issues', [
            'store_id' => $pharmacy->id,
            'items' => [['item_id' => $morphine->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_dispense_posts_an_inventory_movement(): void
    {
        $med = Medication::query()->where('sku', 'PCM-500')->firstOrFail();
        $before = (int) $med->stock_qty;

        Sanctum::actingAs($this->user('reception@riverside.test'));
        $patient = $this->postJson('/api/patients', [
            'first_name' => 'Inventory',
            'last_name' => 'Patient',
            'sex' => 'female',
        ])->assertCreated();

        Sanctum::actingAs($this->user('doctor@riverside.test'));
        $encounter = $this->postJson('/api/encounters', [
            'patient_id' => $patient->json('id'),
            'type' => 'opd',
        ])->assertCreated();
        $rx = $this->postJson('/api/prescriptions', [
            'encounter_id' => $encounter->json('id'),
            'items' => [[
                'medication_id' => $med->id,
                'dose' => '500mg',
                'frequency' => 'tds',
                'quantity' => 8,
            ]],
        ])->assertCreated();

        Sanctum::actingAs($this->user('pharmacy@riverside.test'));
        $this->patchJson('/api/prescriptions/'.$rx->json('id').'/status', ['status' => 'dispensed'])
            ->assertOk()
            ->assertJsonPath('status', 'dispensed');

        $this->assertSame($before - 8, (int) $med->fresh()->stock_qty);
        $this->assertTrue(InventoryMovement::query()->where('type', 'dispense')->where('item_id', $med->inventoryItem->id)->exists());
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
