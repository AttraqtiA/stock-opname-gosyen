<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        DB::table('companies')->orderBy('id')->each(function ($company): void {
            DB::table('warehouses')->insert([
                'company_id' => $company->id,
                'name' => 'Utama',
                'location' => $company->location ?: 'Lokasi Utama',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
