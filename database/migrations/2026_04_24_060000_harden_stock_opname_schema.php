<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->unsignedInteger('next_stock_number')->default(1)->after('code_prefix');
        });

        DB::table('companies')->orderBy('id')->get()->each(function ($company): void {
            $maxNumber = DB::table('stock_items')
                ->where('company_id', $company->id)
                ->where('code', 'like', $company->code_prefix.'-%')
                ->pluck('code')
                ->map(function (string $code) use ($company): int {
                    $suffix = Str::after($code, $company->code_prefix.'-');

                    return ctype_digit($suffix) ? (int) $suffix : 0;
                })
                ->max() ?? 0;

            DB::table('companies')->where('id', $company->id)->update([
                'next_stock_number' => $maxNumber + 1,
            ]);
        });

        $fallbackCompanyId = DB::table('companies')->orderBy('id')->value('id');
        if ($fallbackCompanyId) {
            DB::table('stock_items')->whereNull('company_id')->update([
                'company_id' => $fallbackCompanyId,
            ]);
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY company_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'type', 'name']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->index(['stock_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropIndex(['stock_item_id', 'created_at']);
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name']);
            $table->dropIndex(['company_id', 'type', 'name']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stock_items MODIFY company_id BIGINT UNSIGNED NULL');
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('next_stock_number');
        });
    }
};
