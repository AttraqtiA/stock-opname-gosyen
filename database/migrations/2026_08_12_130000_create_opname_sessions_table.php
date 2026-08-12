<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 50)->default('active'); // active, pending_approval, completed
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('opname_session_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opname_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained()->cascadeOnDelete();
            $table->integer('system_stock')->default(0);
            $table->integer('actual_stock')->default(0);
            $table->timestamps();

            $table->unique(['opname_session_id', 'stock_item_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('opname_session_id')->nullable()->after('user_id')->constrained('opname_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['opname_session_id']);
            $table->dropColumn('opname_session_id');
        });

        Schema::dropIfExists('opname_session_items');
        Schema::dropIfExists('opname_sessions');
    }
};
