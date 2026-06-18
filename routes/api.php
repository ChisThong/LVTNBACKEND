<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDonHangController;
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
use App\Http\Controllers\WalletController;
use App\Http\Controllers\VNPayController;
use App\Http\Controllers\AdminWalletController;
use App\Http\Controllers\DanhGiaController;

/*
|--------------------------------------------------------------------------
| API Routes — Marketplace Đặc Sản Miền Nam
|--------------------------------------------------------------------------
|
| Prefix:  /api
| Auth:    Laravel Sanctum (Bearer Token)
| Roles:   Admin(1) | NguoiMua(2) | NguoiBan(3)
| Payment: VNPay Sandbox
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

// ── Sản phẩm — Public ─────────────────────────────────────────────────────
Route::get('/products',      [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
Route::get('/products/{id}/reviews', [DanhGiaController::class, 'index']);
Route::get('/tinh-thanh', [DiaLyController::class, 'getTinh']);
Route::get('/xa',         [DiaLyController::class, 'getXa']);
Route::get('/ap',         [DiaLyController::class, 'getAp']);
Route::get('/Cauchuyensanvat/{id}', [BaiVietController::class, 'getbaiviet']);
Route::get('/randombaiviet',        [BaiVietController::class, 'getRandomBlogs']);
Route::get('/tintuc',               [BaiVietController::class, 'getTinTuc']);

// ── Shop — Public ──────────────────────────────────────────────────────────
Route::get('/shops/{id}', [ShopController::class, 'publicShow']);

// ── Phân loại — Public ────────────────────────────────────────────────────
Route::get('/phan-loai',      [PhanLoaiController::class, 'index'])->name('phanloai.index');
Route::get('/phan-loai/{id}', [PhanLoaiController::class, 'show'])->name('phanloai.show');

// ── VNPay Callbacks — Public (VNPay server/browser gọi vào, không cần Auth)
Route::get('/vnpay/return',  [VNPayController::class, 'returnUrl'])->name('vnpay.return');
Route::post('/vnpay/ipn',    [VNPayController::class, 'ipn'])->name('vnpay.ipn');

// ═══════════════════════════════════════════════════════════════════════════
// PROTECTED — Cần Bearer Token (auth:sanctum)
// ═══════════════════════════════════════════════════════════════════════════
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth chung ─────────────────────────────────────────────────────────
    // ── Auth chung ─────────────────────────────────────────────────────────
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me',           [AuthController::class, 'me'])->name('auth.me');

    // ── Đơn hàng ───────────────────────────────────────────────────────────
    Route::get('/don-hang',      [DonHangController::class, 'index'])->name('donhang.index');
    Route::get('/don-hang/{id}', [DonHangController::class, 'show'])->name('donhang.show');
    Route::post('/reviews',      [DanhGiaController::class, 'guiDanhGia']);

    // ── Wallet ─────────────────────────────────────────────────────────────
    Route::get('/wallet',              [WalletController::class, 'index'])->name('wallet.index');
    Route::get('/wallet/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');
    Route::post('/withdrawals',        [WalletController::class, 'withdraw'])->name('wallet.withdraw');

    // ── VNPay — Tạo thanh toán (throttle 5 req/phút để chống spam) ────────
    Route::middleware('throttle:5,1')
        ->post('/vnpay/create-payment', [VNPayController::class, 'createPayment'])
        ->name('vnpay.createPayment');

    // ── ADMIN only ─────────────────────────────────────────────────────────
    Route::middleware('role:Admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'adminDashboard'])->name('admin.dashboard');

        // Shop
        Route::get('/shops',                          [ShopController::class, 'adminIndex'])->name('admin.shops.index');
        Route::get('/shops/{id}/products',            [ShopController::class, 'shopProducts'])->name('admin.shops.products');
        Route::put('/shops/{id}/approve',             [ShopController::class, 'approve'])->name('admin.shops.approve');
        Route::put('/shops/{id}/reject',              [ShopController::class, 'reject'])->name('admin.shops.reject');
        Route::patch('/shops/{id}/toggle-status',     [ShopController::class, 'toggleStatus']);

        // Sản phẩm
        Route::get('/products',                 [\App\Http\Controllers\AdminProductController::class, 'index']);
        Route::get('/products/{id}',            [\App\Http\Controllers\AdminProductController::class, 'show']);
        Route::put('/products/{id}/hide',       [\App\Http\Controllers\AdminProductController::class, 'hide']);
        Route::put('/products/{id}/restore',    [\App\Http\Controllers\AdminProductController::class, 'restore']);
        Route::put('/products/{id}/approve',    [\App\Http\Controllers\AdminProductController::class, 'approve']);
        Route::put('/products/{id}/reject',     [\App\Http\Controllers\AdminProductController::class, 'reject']);
        Route::patch('/products/{id}/toggle-visibility', [\App\Http\Controllers\AdminProductController::class, 'toggleVisibility']);

        // ── Quản lý bài viết ────────────────────────────────────────────────
        Route::get('/BlogControl',         [BaiVietController::class, 'index']);
        Route::post('/BlogControl',        [BaiVietController::class, 'store']);
        Route::delete('/BlogControl/{id}', [BaiVietController::class, 'destroy']);
        Route::get('/BlogControl/{id}',    [BaiVietController::class, 'show']);
        Route::put('/BlogControl/{id}',    [BaiVietController::class, 'update']);

        // ── Quản lý Map ─────────────────────────────────────────────────────
        Route::get('/bandoControl',         [VungMienController::class, 'index']);
        Route::post('/bandoControl',        [VungMienController::class, 'store']);
        Route::put('/bandoControl/{id}',    [VungMienController::class, 'update']);
        Route::delete('/bandoControl/{id}', [VungMienController::class, 'destroy']);

        // Quản lý người dùng
        Route::get('/Nguoidung',                    [NguoiDungController::class, 'index']);
        Route::put('/Nguoidung/{id}/ChangeClock',   [NguoiDungController::class, 'changeclock']);
        Route::put('/Nguoidung/{id}',               [NguoiDungController::class, 'update']);

        // Wallet Admin
        Route::get('/wallet/stats',               [AdminWalletController::class, 'stats']);
        Route::get('/wallet/withdrawals',         [AdminWalletController::class, 'withdrawals']);
        Route::put('/wallet/withdrawals/{id}',    [AdminWalletController::class, 'processWithdrawal']);

        // Quản lý đơn hàng
        Route::get('/DonHang',     [AdminDonHangController::class, 'index']);
        Route::get('/DonHang/{id}',[AdminDonHangController::class, 'chitiet']);
    });

    // ── Admin hoặc NguoiBan — CRUD sản phẩm ───────────────────────────────
    Route::middleware('role:Admin,NguoiBan')->group(function () {
        Route::post('/products',           [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{id}',       [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{id}',    [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/reviews/{id}/reply', [DanhGiaController::class, 'phanhoi']);
    });

    // ── NguoiBan only ──────────────────────────────────────────────────────
    Route::middleware('role:NguoiBan')->group(function () {
        Route::get('/seller/dashboard', [DashboardController::class, 'sellerDashboard'])->name('seller.dashboard');
        Route::get('/seller/products',  [ProductController::class, 'sellerIndex'])->name('seller.products.index');
    });

    // ── Shop cá nhân ───────────────────────────────────────────────────────
    Route::get('/seller/shop',           [ShopController::class, 'myShop'])->name('seller.shop.show');
    Route::put('/seller/shop',           [ShopController::class, 'update'])->name('seller.shop.update');
    Route::post('/seller/shop/register', [ShopController::class, 'register'])->name('seller.shop.register');

    // ── NguoiMua only ──────────────────────────────────────────────────────
    Route::middleware('role:NguoiMua')->group(function () {
        Route::get('/buyer/dashboard', [DashboardController::class, 'buyerDashboard'])->name('buyer.dashboard');
        Route::post('/don-hang',       [DonHangController::class, 'store'])->name('donhang.store');
    });
});