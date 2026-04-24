<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_user_can_create_company(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/stock-opname/companies', [
            'name' => '  PT Gosyen Baru  ',
        ]);

        $company = Company::query()->where('name', 'PT Gosyen Baru')->firstOrFail();

        $response
            ->assertOk()
            ->assertJsonPath('currentCompanyId', $company->id)
            ->assertJsonFragment([
                'name' => 'PT Gosyen Baru',
                'code_prefix' => 'PGB',
            ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'PT Gosyen Baru',
            'code_prefix' => 'PGB',
            'status' => 'approved',
        ]);
    }

    public function test_duplicate_company_name_returns_clear_validation_message(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);
        Company::create([
            'name' => 'PT Gosyen Baru',
            'code_prefix' => 'PGB',
        ]);

        $response = $this->actingAs($user)->postJson('/stock-opname/companies', [
            'name' => 'PT Gosyen Baru',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name'])
            ->assertJsonPath('errors.name.0', 'Company dengan nama ini sudah ada.');
    }

    public function test_employee_can_request_company_for_admin_approval(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/stock-opname/companies', [
            'name' => 'PT Employee Client',
            'location' => 'Gudang Selatan',
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('requestAccepted', true);

        $this->assertDatabaseHas('companies', [
            'name' => 'PT Employee Client',
            'location' => 'Gudang Selatan',
            'status' => 'pending',
            'requested_by_user_id' => $user->id,
        ]);
    }
}
