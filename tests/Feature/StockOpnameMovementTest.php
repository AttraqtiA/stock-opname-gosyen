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
}
