<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpnameSessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'opname_session_id',
        'stock_item_id',
        'system_stock',
        'actual_stock',
    ];

    protected $casts = [
        'system_stock' => 'integer',
        'actual_stock' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpnameSession::class, 'opname_session_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id');
    }
}
