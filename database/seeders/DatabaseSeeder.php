<?php

namespace Database\Seeders;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $items = [
            ['code' => 'GSY-KMS-001', 'name' => 'Plastik Vacuum 1 kg', 'type' => 'Kemasan', 'unit' => 'pcs', 'system_stock' => 240, 'actual_stock' => 238],
            ['code' => 'GSY-BHN-014', 'name' => 'Bawang Putih Kupas', 'type' => 'Bahan Baku', 'unit' => 'kg', 'system_stock' => 32, 'actual_stock' => 36],
            ['code' => 'GSY-JDI-022', 'name' => 'Bakso Sapi Premium', 'type' => 'Barang Jadi', 'unit' => 'pack', 'system_stock' => 118, 'actual_stock' => 118],
            ['code' => 'GSY-BKU-009', 'name' => 'Tepung Tapioka', 'type' => 'Bahan Baku', 'unit' => 'kg', 'system_stock' => 75, 'actual_stock' => 69],
            ['code' => 'GSY-KMS-002', 'name' => 'Label Gosyen Frozen', 'type' => 'Kemasan', 'unit' => 'roll', 'system_stock' => 18, 'actual_stock' => 18],
            ['code' => 'GSY-JDI-031', 'name' => 'Siomay Ayam Mini', 'type' => 'Barang Jadi', 'unit' => 'pack', 'system_stock' => 86, 'actual_stock' => 91],
        ];

        foreach ($items as $item) {
            $stockItem = StockItem::updateOrCreate(['code' => $item['code']], $item);

            StockMovement::firstOrCreate([
                'stock_item_id' => $stockItem->id,
                'kind' => 'count',
                'note' => 'Data testing awal',
            ], [
                'quantity' => $stockItem->actual_stock,
                'system_stock_before' => $stockItem->system_stock,
                'actual_stock_before' => $stockItem->actual_stock,
                'system_stock_after' => $stockItem->system_stock,
                'actual_stock_after' => $stockItem->actual_stock,
                'location' => 'Gudang Utama',
                'officer' => 'Tim Gosyen',
            ]);
        }
    }
}
