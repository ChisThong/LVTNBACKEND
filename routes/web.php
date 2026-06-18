<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VNPayController;

Route::get('/', function () {
    return view('welcome');
});

// ── VNPay Return URL ──────────────────────────────────────────────────────────
// VNPay redirect trình duyệt người dùng về đây sau khi thanh toán.
// Route này KHÔNG có prefix /api vì VNPay gọi trực tiếp qua ngrok URL.
Route::get('/vnpay-return', [VNPayController::class, 'vnpayReturn'])->name('vnpay.browser.return');
