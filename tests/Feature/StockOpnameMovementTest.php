<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_count_movements_overwrite_opname_quantity(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'is_approved' => true,
        ]);
        $company = Company::create([
            'name' => 'PT Warehouse Client',
            'code_prefix' => 'PWC',
            'next_stock_number' => 1,
            'status' => 'approved',
        ]);
        $item = StockItem::create([
            'company_id' => $company->id,
            'code' => 'PWC-0001',
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'normalized_type' => 'sparepart',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 0,
        ]);

        $this->actingAs($user)->postJson('/stock-opname/movements', [
            'company_id' => $company->id,
            'stock_item_id' => $item->id,
            'kind' => 'count',
            'quantity' => 3,
            'location' => 'Rak A',
            'officer' => 'Budi',
        ])->assertOk();

        $this->assertSame(3, $item->fresh()->actual_stock);

        $this->actingAs($user)->postJson('/stock-opname/movements', [
            'company_id' => $company->id,
            'stock_item_id' => $item->id,
            'kind' => 'count',
            'quantity' => 2,
            'location' => 'Rak A',
            'officer' => 'Sari',
        ])->assertOk();

        $this->assertSame(2, $item->fresh()->actual_stock);
    }

    public function test_product_type_and_unit_can_be_updated(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'is_approved' => true,
        ]);
        $company = Company::create([
            'name' => 'PT Warehouse Client',
            'code_prefix' => 'PWC',
            'next_stock_number' => 1,
        ]);
        $item = StockItem::create([
            'company_id' => $company->id,
            'code' => 'PWC-0001',
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'normalized_type' => 'sparepart',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 0,
        ]);

        $this->actingAs($user)->patchJson("/stock-opname/items/{$item->id}", [
            'company_id' => $company->id,
            'type' => 'Barang Jadi',
            'unit' => 'pack',
        ])->assertOk()
            ->assertJsonPath('products.0.type', 'Barang Jadi')
            ->assertJsonPath('products.0.unit', 'pack');

        $item->refresh();

        $this->assertSame('Barang Jadi', $item->type);
        $this->assertSame('barangjadi', $item->normalized_type);
        $this->assertSame('pack', $item->unit);
        $this->assertDatabaseHas('stock_movements', [
            'stock_item_id' => $item->id,
            'kind' => 'update',
            'note' => 'Edit tipe & satuan',
            'user_id' => $user->id,
        ]);
    }

    public function test_employee_cannot_update_system_stock(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Warehouse Client', 'code_prefix' => 'PWC', 'next_stock_number' => 1, 'status' => 'approved']);
        $item = StockItem::create([
            'company_id' => $company->id,
            'code' => 'PWC-0001',
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'normalized_type' => 'sparepart',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 0,
        ]);

        $this->actingAs($employee)->patchJson("/stock-opname/items/{$item->id}", [
            'company_id' => $company->id,
            'system_stock' => 20,
        ])->assertStatus(403);

        $this->assertSame(10, $item->fresh()->system_stock);
    }

    public function test_employee_cannot_delete_product(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Warehouse Client', 'code_prefix' => 'PWC', 'next_stock_number' => 1, 'status' => 'approved']);
        $item = StockItem::create([
            'company_id' => $company->id,
            'code' => 'PWC-0001',
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'normalized_type' => 'sparepart',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 0,
        ]);

        $this->actingAs($employee)->deleteJson("/stock-opname/items/{$item->id}", [
            'company_id' => $company->id,
        ])->assertStatus(403);

        $this->assertNull($item->fresh()->deleted_at);
    }

    public function test_cannot_access_pending_company_data(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Pending Client', 'code_prefix' => 'PPC', 'next_stock_number' => 1, 'status' => 'pending']);

        $this->actingAs($user)->getJson("/stock-opname?company_id={$company->id}")
            ->assertNotFound();

        $this->actingAs($user)->get("/stock-opname/history?company_id={$company->id}")
            ->assertNotFound();
    }

    public function test_can_create_product_with_same_name_after_soft_delete(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Warehouse Client', 'code_prefix' => 'PWC', 'next_stock_number' => 1, 'status' => 'approved']);
        $warehouse = $company->warehouses()->firstOrFail();

        // Create first product
        $this->actingAs($user)->postJson('/stock-opname/items', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 0,
        ])->assertStatus(201);

        $item = StockItem::query()->where('name', '02HA Precision Regulator')->firstOrFail();

        // Delete first product (soft delete)
        $this->actingAs($user)->deleteJson("/stock-opname/items/{$item->id}", [
            'company_id' => $company->id,
        ])->assertOk();

        $this->assertNotNull($item->fresh()->deleted_at);

        // Try creating with same name again - should succeed now!
        $this->actingAs($user)->postJson('/stock-opname/items', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'unit' => 'pcs',
            'system_stock' => 5,
            'actual_stock' => 0,
        ])->assertStatus(201);

        $this->assertSame(2, StockItem::withTrashed()->where('name', '02HA Precision Regulator')->count());
    }

    public function test_history_shows_trashed_product_details(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Warehouse Client', 'code_prefix' => 'PWC', 'next_stock_number' => 1, 'status' => 'approved']);
        
        $item = StockItem::create([
            'company_id' => $company->id,
            'code' => 'PWC-0001',
            'name' => '02HA Precision Regulator',
            'type' => 'Sparepart',
            'normalized_type' => 'sparepart',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 0,
        ]);

        // Record a movement
        $this->actingAs($user)->postJson('/stock-opname/movements', [
            'company_id' => $company->id,
            'stock_item_id' => $item->id,
            'kind' => 'count',
            'quantity' => 3,
        ])->assertOk();

        // Delete the item (soft delete)
        $this->actingAs($user)->deleteJson("/stock-opname/items/{$item->id}", [
            'company_id' => $company->id,
        ])->assertOk();

        // Load history and verify it shows the product name instead of 'Produk dihapus'
        $response = $this->actingAs($user)->get("/stock-opname/history?company_id={$company->id}");
        $response->assertOk();
        $response->assertSee('02HA Precision Regulator');
    }
}
