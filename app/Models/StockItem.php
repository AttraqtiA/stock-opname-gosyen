<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (StockItem $item) {
            if (!$item->warehouse_id && $item->company_id) {
                $item->warehouse_id = Warehouse::query()
                    ->where('company_id', $item->company_id)
                    ->orderBy('id')
                    ->value('id');
            }
        });
    }

    protected $fillable = [
        'code',
        'company_id',
        'warehouse_id',
        'name',
        'type',
        'normalized_type',
        'unit',
        'system_stock',
        'actual_stock',
    ];

    protected $casts = [
        'system_stock' => 'integer',
        'actual_stock' => 'integer',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
