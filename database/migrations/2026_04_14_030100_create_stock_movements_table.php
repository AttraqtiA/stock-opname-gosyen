<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('system_stock_before')->default(0);
            $table->unsignedInteger('actual_stock_before')->default(0);
            $table->unsignedInteger('system_stock_after')->default(0);
            $table->unsignedInteger('actual_stock_after')->default(0);
            $table->string('location')->nullable();
            $table->string('officer')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
