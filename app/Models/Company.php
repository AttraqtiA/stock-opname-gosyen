<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'pic_user_id',
        'code_prefix',
        'next_stock_number',
        'status',
        'requested_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    protected $casts = [
        'next_stock_number' => 'integer',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Company $company) {
            $company->warehouses()->create([
                'name' => 'Utama',
                'location' => $company->location ?: 'Lokasi Utama',
            ]);
        });
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
