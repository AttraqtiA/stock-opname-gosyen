<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_company_automatically_creates_utama_warehouse(): void
    {
        $company = Company::create([
            'name' => 'PT Test Automata',
            'code_prefix' => 'PTA',
        ]);

        $this->assertDatabaseHas('warehouses', [
            'company_id' => $company->id,
            'name' => 'Utama',
        ]);
    }

    public function test_warehouse_crud_actions(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Test Automata', 'code_prefix' => 'PTA', 'status' => 'approved']);

        // Create warehouse
        $response = $this->actingAs($user)->postJson('/stock-opname/warehouses', [
            'company_id' => $company->id,
            'name' => 'Gudang Barat',
            'location' => 'Blok B',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('warehouses', [
            'company_id' => $company->id,
            'name' => 'Gudang Barat',
            'location' => 'Blok B',
        ]);

        $warehouse = Warehouse::where('name', 'Gudang Barat')->firstOrFail();

        // Update warehouse
        $this->actingAs($user)->patchJson("/stock-opname/warehouses/{$warehouse->id}", [
            'company_id' => $company->id,
            'name' => 'Gudang Barat Revisi',
            'location' => 'Blok C',
        ])->assertOk();

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Gudang Barat Revisi',
            'location' => 'Blok C',
        ]);

        // Delete warehouse
        $this->actingAs($user)->deleteJson("/stock-opname/warehouses/{$warehouse->id}", [
            'company_id' => $company->id,
        ])->assertOk();

        $this->assertDatabaseMissing('warehouses', [
            'id' => $warehouse->id,
        ]);
    }

    public function test_cannot_delete_last_warehouse(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Test Automata', 'code_prefix' => 'PTA', 'status' => 'approved']);

        $utama = $company->warehouses()->firstOrFail();

        $this->actingAs($user)->deleteJson("/stock-opname/warehouses/{$utama->id}", [
            'company_id' => $company->id,
        ])->assertStatus(422);

        $this->assertDatabaseHas('warehouses', [
            'id' => $utama->id,
        ]);
    }

    public function test_cannot_delete_warehouse_with_items(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $company = Company::create(['name' => 'PT Test Automata', 'code_prefix' => 'PTA', 'status' => 'approved']);

        $utama = $company->warehouses()->firstOrFail();

        // Add item
        StockItem::create([
            'company_id' => $company->id,
            'warehouse_id' => $utama->id,
            'code' => 'PTA-0001',
            'name' => 'Test Item',
            'type' => 'Tipe',
            'normalized_type' => 'tipe',
            'unit' => 'pcs',
            'system_stock' => 10,
            'actual_stock' => 10,
        ]);

        $this->actingAs($user)->deleteJson("/stock-opname/warehouses/{$utama->id}", [
            'company_id' => $company->id,
        ])->assertStatus(422);
    }
}
