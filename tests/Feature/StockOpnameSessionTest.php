<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\StockItem;
use App\Models\OpnameSession;
use App\Models\OpnameSessionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private Company $company;
    private StockItem $item1;
    private StockItem $item2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $this->employee = User::factory()->create([
            'role' => 'employee',
            'is_approved' => true,
        ]);

        $this->company = Company::create([
            'name' => 'Gosyen Test Company',
            'code_prefix' => 'GTC',
            'next_stock_number' => 1,
            'status' => 'approved',
        ]);

        // Create warehouse
        $warehouse = $this->company->warehouses()->create([
            'name' => 'Gudang Utama',
        ]);

        $this->item1 = StockItem::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'GTC-001',
            'name' => 'Barang A',
            'type' => 'Bahan Baku',
            'normalized_type' => 'bahanbaku',
            'unit' => 'kg',
            'system_stock' => 10,
            'actual_stock' => 10,
        ]);

        $this->item2 = StockItem::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $warehouse->id,
            'code' => 'GTC-002',
            'name' => 'Barang B',
            'type' => 'Bahan Baku',
            'normalized_type' => 'bahanbaku',
            'unit' => 'kg',
            'system_stock' => 50,
            'actual_stock' => 50,
        ]);
    }

    public function test_cannot_record_opname_count_without_active_session(): void
    {
        // Try count movement without active session - should fail with 422
        $this->actingAs($this->employee)->postJson('/stock-opname/movements', [
            'company_id' => $this->company->id,
            'stock_item_id' => $this->item1->id,
            'kind' => 'count',
            'quantity' => 12,
        ])->assertStatus(422);
    }

    public function test_can_create_opname_session_and_populates_items(): void
    {
        $response = $this->actingAs($this->employee)->postJson('/stock-opname/sessions', [
            'company_id' => $this->company->id,
            'name' => 'Opname Akhir Semester 1',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('opname_sessions', [
            'company_id' => $this->company->id,
            'name' => 'Opname Akhir Semester 1',
            'status' => 'active',
            'created_by_user_id' => $this->employee->id,
        ]);

        $session = OpnameSession::where('company_id', $this->company->id)->first();

        // Check if items are pre-populated and system stocks snapshot is correct
        $this->assertDatabaseHas('opname_session_items', [
            'opname_session_id' => $session->id,
            'stock_item_id' => $this->item1->id,
            'system_stock' => 10,
            'actual_stock' => 0, // Starts at 0 count
        ]);

        $this->assertDatabaseHas('opname_session_items', [
            'opname_session_id' => $session->id,
            'stock_item_id' => $this->item2->id,
            'system_stock' => 50,
            'actual_stock' => 0, // Starts at 0 count
        ]);
    }

    public function test_opname_movements_update_session_item_stock(): void
    {
        // Start session
        $session = OpnameSession::create([
            'company_id' => $this->company->id,
            'name' => 'Test Session',
            'status' => 'active',
            'created_by_user_id' => $this->employee->id,
        ]);

        OpnameSessionItem::create([
            'opname_session_id' => $session->id,
            'stock_item_id' => $this->item1->id,
            'system_stock' => 10,
            'actual_stock' => 0,
        ]);

        // Submit count
        $this->actingAs($this->employee)->postJson('/stock-opname/movements', [
            'company_id' => $this->company->id,
            'stock_item_id' => $this->item1->id,
            'kind' => 'count',
            'quantity' => 12,
        ])->assertOk();

        // Session item actual stock should be updated
        $sessionItem = OpnameSessionItem::where('opname_session_id', $session->id)
            ->where('stock_item_id', $this->item1->id)
            ->first();

        $this->assertEquals(12, $sessionItem->actual_stock);
        $this->assertEquals(12, $this->item1->fresh()->actual_stock);
    }

    public function test_employee_finalizing_with_large_discrepancy_needs_approval(): void
    {
        $session = OpnameSession::create([
            'company_id' => $this->company->id,
            'name' => 'Test Session',
            'status' => 'active',
            'created_by_user_id' => $this->employee->id,
        ]);

        // Create session items
        // Item 1: system = 10, actual = 25 (difference = 15 >= 10)
        OpnameSessionItem::create([
            'opname_session_id' => $session->id,
            'stock_item_id' => $this->item1->id,
            'system_stock' => 10,
            'actual_stock' => 25,
        ]);

        $response = $this->actingAs($this->employee)->postJson("/stock-opname/sessions/{$session->id}/finalize", [
            'company_id' => $this->company->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('pending', true);

        // Status should be pending_approval
        $this->assertEquals('pending_approval', $session->fresh()->status);
        // System stock should NOT be updated yet
        $this->assertEquals(10, $this->item1->fresh()->system_stock);
    }

    public function test_employee_finalizing_with_small_discrepancy_completes_instantly(): void
    {
        $session = OpnameSession::create([
            'company_id' => $this->company->id,
            'name' => 'Test Session',
            'status' => 'active',
            'created_by_user_id' => $this->employee->id,
        ]);

        // Item 1: system = 10, actual = 12 (difference = 2 < 10)
        OpnameSessionItem::create([
            'opname_session_id' => $session->id,
            'stock_item_id' => $this->item1->id,
            'system_stock' => 10,
            'actual_stock' => 12,
        ]);

        $response = $this->actingAs($this->employee)->postJson("/stock-opname/sessions/{$session->id}/finalize", [
            'company_id' => $this->company->id,
        ]);

        $response->assertOk();
        $response->assertJsonMissing(['pending' => true]);

        // Status should be completed
        $this->assertEquals('completed', $session->fresh()->status);
        // System stock should be updated to actual
        $this->assertEquals(12, $this->item1->fresh()->system_stock);
    }

    public function test_admin_can_approve_pending_session(): void
    {
        $session = OpnameSession::create([
            'company_id' => $this->company->id,
            'name' => 'Test Session',
            'status' => 'pending_approval',
            'created_by_user_id' => $this->employee->id,
        ]);

        OpnameSessionItem::create([
            'opname_session_id' => $session->id,
            'stock_item_id' => $this->item1->id,
            'system_stock' => 10,
            'actual_stock' => 25,
        ]);

        // Admin approves
        $this->actingAs($this->admin)->postJson("/stock-opname/sessions/{$session->id}/approve", [
            'company_id' => $this->company->id,
        ])->assertOk();

        $this->assertEquals('completed', $session->fresh()->status);
        $this->assertEquals(25, $this->item1->fresh()->system_stock);
    }

    public function test_admin_can_reject_pending_session_reverts_to_active(): void
    {
        $session = OpnameSession::create([
            'company_id' => $this->company->id,
            'name' => 'Test Session',
            'status' => 'pending_approval',
            'created_by_user_id' => $this->employee->id,
        ]);

        // Admin rejects
        $this->actingAs($this->admin)->postJson("/stock-opname/sessions/{$session->id}/reject", [
            'company_id' => $this->company->id,
        ])->assertOk();

        $this->assertEquals('active', $session->fresh()->status);
    }
}
