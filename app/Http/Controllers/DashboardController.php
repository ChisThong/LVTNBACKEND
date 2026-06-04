<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard dành cho Admin.
     *
     * GET /api/admin/dashboard
     * Middleware: auth:sanctum, role:Admin
     */
    public function adminDashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Chào mừng đến trang quản trị.',
            'data'    => [
                'user'      => [
                    'ID_User'  => $user->ID_User,
                    'HoTen'    => $user->HoTen,
                    'email'    => $user->email,
                    'Ten_role' => $user->role?->Ten_role,
                ],
                'dashboard' => 'Admin Dashboard',
                'stats'     => [
                    'total_users'    => \App\Models\User::count(),
                    'total_products' => \App\Models\SanPham::count(),
                    'total_orders'   => \App\Models\DonHang::count(),
                ],
            ],
        ]);
    }

    /**
     * Dashboard dành cho Người Bán.
     *
     * GET /api/seller/dashboard
     * Middleware: auth:sanctum, role:NguoiBan
     */
    public function sellerDashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Chào mừng Người Bán.',
            'data'    => [
                'user'      => [
                    'ID_User'  => $user->ID_User,
                    'HoTen'    => $user->HoTen,
                    'email'    => $user->email,
                    'Ten_role' => $user->role?->Ten_role,
                ],
                'dashboard' => 'Seller Dashboard',
                'guide'     => [
                    'Quản lý sản phẩm' => 'GET /api/san-pham (sắp triển khai)',
                    'Xem đơn hàng'      => 'GET /api/don-hang (sắp triển khai)',
                ],
            ],
        ]);
    }

    /**
     * Dashboard dành cho Người Mua.
     *
     * GET /api/buyer/dashboard
     * Middleware: auth:sanctum, role:NguoiMua
     */
    public function buyerDashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json([
            'success' => true,
            'message' => 'Chào mừng Người Mua.',
            'data'    => [
                'user'      => [
                    'ID_User'  => $user->ID_User,
                    'HoTen'    => $user->HoTen,
                    'email'    => $user->email,
                    'Ten_role' => $user->role?->Ten_role,
                ],
                'dashboard' => 'Buyer Dashboard',
                'guide'     => [
                    'Xem sản phẩm'  => 'GET /api/san-pham (sắp triển khai)',
                    'Đặt hàng'      => 'POST /api/don-hang (sắp triển khai)',
                    'Đánh giá'      => 'POST /api/danh-gia (sắp triển khai)',
                ],
            ],
        ]);
    }
}
