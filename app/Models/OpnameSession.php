<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpnameSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'status', // active, pending_approval, completed
        'created_by_user_id',
        'completed_by_user_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OpnameSessionItem::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
