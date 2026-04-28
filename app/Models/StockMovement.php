<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_item_id',
        'user_id',
        'kind',
        'quantity',
        'system_stock_before',
        'actual_stock_before',
        'system_stock_after',
        'actual_stock_after',
        'location',
        'officer',
        'note',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'system_stock_before' => 'integer',
        'actual_stock_before' => 'integer',
        'system_stock_after' => 'integer',
        'actual_stock_after' => 'integer',
    ];

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
