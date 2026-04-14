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
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code_prefix', 12)->unique();
            $table->timestamps();
        });

        Schema::table('stock_items', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('normalized_type')->after('type');
            $table->index(['company_id', 'normalized_type']);
        });

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Gosyen Demo Company',
            'code_prefix' => 'GDC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_items')->orderBy('id')->chunkById(100, function ($items) use ($companyId): void {
            foreach ($items as $item) {
                DB::table('stock_items')
                    ->where('id', $item->id)
                    ->update([
                        'company_id' => $companyId,
                        'normalized_type' => Str::of($item->type)->lower()->replaceMatches('/\s+/', '')->toString(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id', 'normalized_type']);
            $table->dropColumn(['company_id', 'normalized_type']);
        });

        Schema::dropIfExists('companies');
    }
};
