<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StockOpnameController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->middleware('auth')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('admin')->group(function (): void {
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('admin.users.approve');
});

Route::middleware('auth')->prefix('stock-opname')->group(function (): void {
    Route::get('/', [StockOpnameController::class, 'index'])->name('stock-opname.index');
    Route::post('/companies', [StockOpnameController::class, 'storeCompany'])->name('stock-opname.companies.store');
    Route::post('/items', [StockOpnameController::class, 'storeItem'])->name('stock-opname.items.store');
    Route::post('/movements', [StockOpnameController::class, 'storeMovement'])->name('stock-opname.movements.store');
    Route::get('/export', [StockOpnameController::class, 'export'])->name('stock-opname.export');
});
