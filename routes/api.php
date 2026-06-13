<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DonHangController;
use App\Http\Controllers\PhanLoaiController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SanPhamController;
use App\Http\Controllers\BaiVietController;
use App\Http\Controllers\DiaLyController;
use App\Http\Controllers\NguoiDungController;
use App\Http\Controllers\VungMienController;
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
    Route::post('/register',   [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login',      [AuthController::class, 'login'])->name('auth.login');
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('auth.verify-otp');
    Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->name('auth.resend-otp');
});

// ── Module 2: Sản phẩm — Public (mọi người xem) ──────────────────────────
Route::get('/products',      [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
Route::get('/tinh-thanh', [DiaLyController::class, 'getTinh']);
Route::get('/xa', [DiaLyController::class, 'getXa']);
Route::get('/ap', [DiaLyController::class, 'getAp']);

// ── Shop — Public ──────────────────────────────────────────────
Route::get('/shops/{id}', [ShopController::class, 'publicShow']);


// ── Module 3: Phân loại sản phẩm — Public ──────────────────────────────────────
Route::get('/phan-loai',      [PhanLoaiController::class, 'index'])->name('phanloai.index');
Route::get('/phan-loai/{id}', [PhanLoaiController::class, 'show'])->name('phanloai.show');

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
        Route::get('/dashboard',         [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');
        
        // ── Module 4: Shop — Admin quản lý duyệt ──────────────────────────────
        Route::get('/shops',             [ShopController::class,     'adminIndex'])->name('admin.shops.index');
        Route::get('/shops/{id}/products',[ShopController::class,    'shopProducts'])->name('admin.shops.products');
        Route::put('/shops/{id}/approve',[ShopController::class,     'approve'])->name('admin.shops.approve');
        Route::put('/shops/{id}/reject', [ShopController::class,     'reject'])->name('admin.shops.reject');
        Route::patch('/shops/{id}/toggle-status', [ShopController::class, 'toggleStatus']);

        // ── Admin Quản lý Sản phẩm ─────────────────────────────────────────────
        Route::get('/products',          [\App\Http\Controllers\AdminProductController::class, 'index']);
        Route::get('/products/{id}',     [\App\Http\Controllers\AdminProductController::class, 'show']);
        Route::put('/products/{id}/hide',            [\App\Http\Controllers\AdminProductController::class, 'hide']);
        Route::put('/products/{id}/restore',         [\App\Http\Controllers\AdminProductController::class, 'restore']);
        Route::put('/products/{id}/approve',         [\App\Http\Controllers\AdminProductController::class, 'approve']);
        Route::put('/products/{id}/reject',          [\App\Http\Controllers\AdminProductController::class, 'reject']);
        Route::patch('/products/{id}/toggle-visibility', [\App\Http\Controllers\AdminProductController::class, 'toggleVisibility']);

        
        // ── Quản lý bài viết ────────────────────────────────────────────────
        Route::get('/BlogControl', [BaiVietController::class, 'index']);
        Route::post('/BlogControl', [BaiVietController::class, 'store']);
        Route::delete('/BlogControl/{id}', [BaiVietController::class, 'destroy']);
        Route::get('/BlogControl/{id}', [BaiVietController::class, 'show']);
        Route::put('/BlogControl/{id}', [BaiVietController::class, 'update']);
        
        // ── Quản lý Map ─────────────────────────────────────────────────────
        Route::get('/bandoControl', [VungMienController::class, 'index']);
        Route::post('/bandoControl', [VungMienController::class, 'store']);
        Route::put('/bandoControl/{id}', [VungMienController::class, 'update']);
        Route::delete('/bandoControl/{id}', [VungMienController::class, 'destroy']);
        // Quản lý người dùng
        Route::get('/Nguoidung',[NguoiDungController::class,'index']);
        Route::put('/Nguoidung/{id}/ChangeClock',[NguoiDungController::class,'changeclock']);
        Route::put('/Nguoidung/{id}',[NguoiDungController::class,'update']);

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

    // ── Module 4: Shop (Gian hàng) — Quản lý shop của user (Đăng ký, Xem, Cập nhật) ──
    Route::get('/seller/shop',              [ShopController::class, 'myShop'])->name('seller.shop.show');
    Route::put('/seller/shop',              [ShopController::class, 'update'])->name('seller.shop.update');
    Route::post('/seller/shop/register',    [ShopController::class, 'register'])->name('seller.shop.register');

    // ── NGUOI MUA only ─────────────────────────────────────────────────────
    Route::middleware('role:NguoiMua')->group(function () {
        Route::get('/buyer/dashboard', [DashboardController::class, 'buyerDashboard'])->name('buyer.dashboard');
        Route::post('/don-hang',       [DonHangController::class, 'store'])->name('donhang.store');
    });
});
