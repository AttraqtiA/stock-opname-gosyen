<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('company_id')->constrained()->cascadeOnDelete();
        });

        DB::table('stock_items')->orderBy('id')->chunkById(100, function ($items): void {
            foreach ($items as $item) {
                $warehouseId = DB::table('warehouses')
                    ->where('company_id', $item->company_id)
                    ->where('name', 'Utama')
                    ->value('id');

                if ($warehouseId) {
                    DB::table('stock_items')
                        ->where('id', $item->id)
                        ->update(['warehouse_id' => $warehouseId]);
                }
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->foreignId('warehouse_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
