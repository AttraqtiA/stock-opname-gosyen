<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('stock_items')
                ->whereNotNull('company_id')
                ->orderBy('id')
                ->get()
                ->each(function ($item): void {
                    DB::table('stock_items')->where('id', $item->id)->update([
                        'code' => 'TMP-'.$item->id,
                    ]);
                });

            DB::table('companies')->orderBy('id')->get()->each(function ($company): void {
                DB::table('stock_items')
                    ->where('company_id', $company->id)
                    ->orderBy('id')
                    ->get()
                    ->values()
                    ->each(function ($item, int $index) use ($company): void {
                        DB::table('stock_items')->where('id', $item->id)->update([
                            'code' => sprintf('%s-%03d', $company->code_prefix, $index + 1),
                        ]);
                    });
            });
        });
    }

    public function down(): void
    {
        //
    }
};
