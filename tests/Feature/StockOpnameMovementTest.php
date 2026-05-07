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

    public function test_count_movements_accumulate_opname_quantity(): void
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

        $this->actingAs($user)->postJson('/stock-opname/movements', [
            'company_id' => $company->id,
            'stock_item_id' => $item->id,
            'kind' => 'count',
            'quantity' => 3,
            'location' => 'Rak A',
            'officer' => 'Budi',
        ])->assertOk();

        $this->actingAs($user)->postJson('/stock-opname/movements', [
            'company_id' => $company->id,
            'stock_item_id' => $item->id,
            'kind' => 'count',
            'quantity' => 2,
            'location' => 'Rak A',
            'officer' => 'Sari',
        ])->assertOk();

        $this->assertSame(5, $item->fresh()->actual_stock);
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
}
