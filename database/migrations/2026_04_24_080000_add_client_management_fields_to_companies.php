<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('location')->nullable()->after('name');
            $table->foreignId('pic_user_id')->nullable()->after('location')->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('approved')->after('next_stock_number');
            $table->foreignId('requested_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->after('requested_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->index(['status', 'name']);
        });

        DB::table('companies')->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropForeign(['pic_user_id']);
            $table->dropForeign(['requested_by_user_id']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropIndex(['status', 'name']);
            $table->dropColumn([
                'location',
                'pic_user_id',
                'status',
                'requested_by_user_id',
                'approved_by_user_id',
                'approved_at',
            ]);
        });
    }
};
