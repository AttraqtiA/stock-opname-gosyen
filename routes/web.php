<?php

use App\Http\Controllers\StockOpnameController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('stock-opname')->group(function (): void {
    Route::get('/', [StockOpnameController::class, 'index'])->name('stock-opname.index');
    Route::post('/items', [StockOpnameController::class, 'storeItem'])->name('stock-opname.items.store');
    Route::post('/movements', [StockOpnameController::class, 'storeMovement'])->name('stock-opname.movements.store');
    Route::get('/export', [StockOpnameController::class, 'export'])->name('stock-opname.export');
});
