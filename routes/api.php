<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonHangController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SanPhamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Marketplace Đặc Sản Miền Nam
|--------------------------------------------------------------------------
|
| Prefix:  /api
| Auth:    Laravel Sanctum (Bearer Token)
| Roles:   Admin(1) | NguoiMua(2) | NguoiBan(3)
|
*/

// ═══════════════════════════════════════════════════════════════════════════
// PUBLIC — Không cần đăng nhập
// ═══════════════════════════════════════════════════════════════════════════

// Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login',    [AuthController::class, 'login'])->name('auth.login');
});

// ── Module 2: Sản phẩm — Public (mọi người xem) ──────────────────────────
Route::get('/products',      [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');

// ═══════════════════════════════════════════════════════════════════════════
// PROTECTED — Cần Bearer Token (auth:sanctum)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth chung ─────────────────────────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me',           [AuthController::class, 'me'])->name('auth.me');

    // ── Đơn hàng — user đã đăng nhập xem đơn của mình ─────────────────────
    Route::get('/don-hang',      [DonHangController::class, 'index'])->name('donhang.index');
    Route::get('/don-hang/{id}', [DonHangController::class, 'show'])->name('donhang.show');

    // ── ADMIN only (HTTP 403 nếu sai role) ────────────────────────────────
    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
    });

    // ── Module 2: CRUD Sản phẩm — Admin hoặc NguoiBan ────────────────────
    // NguoiMua chỉ xem (routes public ở trên), Admin và NguoiBan mới được CUD
    Route::middleware('role:Admin,NguoiBan')->group(function () {
        Route::post('/products',           [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{id}',       [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{id}',    [ProductController::class, 'destroy'])->name('products.destroy');
    });

    // ── NGUOI BAN only ─────────────────────────────────────────────────────
    Route::middleware('role:NguoiBan')->group(function () {
        Route::get('/seller/dashboard', [DashboardController::class, 'sellerDashboard'])->name('seller.dashboard');
    });

    // ── NGUOI MUA only ─────────────────────────────────────────────────────
    Route::middleware('role:NguoiMua')->group(function () {
        Route::get('/buyer/dashboard', [DashboardController::class, 'buyerDashboard'])->name('buyer.dashboard');
        Route::post('/don-hang',       [DonHangController::class, 'store'])->name('donhang.store');
    });
});
