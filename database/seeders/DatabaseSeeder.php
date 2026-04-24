<?php

namespace Database\Seeders;

use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@gosyen'], [
            'name' => 'Gosyen Admin',
            'password' => 'password',
            'role' => 'admin',
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'employee@gosyen.test'], [
            'name' => 'Gosyen Employee',
            'password' => 'password',
            'role' => 'employee',
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $company = Company::updateOrCreate(
            ['code_prefix' => 'GDC'],
            [
                'name' => '[CONTOH] Gosyen El Company',
                'location' => 'Gudang Utama',
                'pic_user_id' => User::query()->where('email', 'admin@gosyen.test')->value('id'),
                'next_stock_number' => 1,
                'status' => 'approved',
                'approved_at' => now(),
            ]
        );

        $items = [
            ['name' => 'Rego Plastik Mundak', 'type' => 'Kemasan', 'unit' => 'pcs', 'system_stock' => 240, 'actual_stock' => 238],
            ['name' => 'Indomie Rendang', 'type' => 'Bahan Baku', 'unit' => 'kg', 'system_stock' => 32, 'actual_stock' => 36],
            ['name' => 'Bakso Sapi Mawardi', 'type' => 'Barang Jadi', 'unit' => 'pack', 'system_stock' => 118, 'actual_stock' => 118],
            ['name' => 'Tepung-tepungan', 'type' => 'Bahan Baku', 'unit' => 'kg', 'system_stock' => 75, 'actual_stock' => 69],
            ['name' => 'Label Gosyen Frozen', 'type' => 'Kemasan', 'unit' => 'roll', 'system_stock' => 18, 'actual_stock' => 18],
            ['name' => 'Ketoprak', 'type' => 'Barang Jadi', 'unit' => 'pack', 'system_stock' => 86, 'actual_stock' => 91],
        ];

        foreach ($items as $item) {
            $stockItem = StockItem::firstOrNew(['company_id' => $company->id, 'name' => $item['name']]);
            $stockItem->fill([
                ...$item,
                'normalized_type' => Str::of($item['type'])->lower()->replaceMatches('/\s+/', '')->toString(),
            ]);

            if (! $stockItem->exists) {
                $stockItem->code = $this->nextStockCode($company->code_prefix);
            }

            $stockItem->save();

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

        $company->update([
            'next_stock_number' => StockItem::query()->where('company_id', $company->id)->count() + 1,
        ]);
    }

    private function nextStockCode(string $prefix): string
    {
        $next = 1;

        do {
            $code = sprintf('%s-%03d', $prefix, $next);
            $next++;
        } while (StockItem::query()->where('code', $code)->exists());

        return $code;
    }
}
