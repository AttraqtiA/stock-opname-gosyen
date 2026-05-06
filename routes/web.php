<?php

use App\Http\Controllers\AdminCompanyController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StockOpnameController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'picUsers' => User::query()
            ->where('is_approved', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']),
    ]);
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
    Route::get('/companies', [AdminCompanyController::class, 'index'])->name('admin.companies');
    Route::post('/companies/{company}/approve', [AdminCompanyController::class, 'approve'])->name('admin.companies.approve');
    Route::put('/companies/{company}', [AdminCompanyController::class, 'update'])->name('admin.companies.update');
    Route::delete('/companies/{company}', [AdminCompanyController::class, 'destroy'])->name('admin.companies.destroy');
});

Route::middleware('auth')->prefix('stock-opname')->group(function (): void {
    Route::get('/', [StockOpnameController::class, 'index'])->name('stock-opname.index');
    Route::post('/companies', [StockOpnameController::class, 'storeCompany'])->name('stock-opname.companies.store');
    Route::post('/items', [StockOpnameController::class, 'storeItem'])->name('stock-opname.items.store');
    Route::patch('/items/{stockItem}', [StockOpnameController::class, 'updateItem'])->name('stock-opname.items.update');
    Route::delete('/items/{stockItem}', [StockOpnameController::class, 'destroyItem'])->name('stock-opname.items.destroy');
    Route::post('/movements', [StockOpnameController::class, 'storeMovement'])->name('stock-opname.movements.store');
    Route::get('/history', [StockOpnameController::class, 'history'])->name('stock-opname.history');
    Route::get('/export', [StockOpnameController::class, 'export'])->name('stock-opname.export');
});
