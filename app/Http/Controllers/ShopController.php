<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\RegisterShopRequest;
use App\Http\Requests\Shop\UpdateShopRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/seller/shop/register
    // Middleware: auth:sanctum (bất kỳ role đã đăng nhập đều được đăng ký)
    // Mỗi user chỉ được có 1 shop.
    // ─────────────────────────────────────────────────────────────────────────
    public function register(RegisterShopRequest $request): JsonResponse
    {
        $user = $request->user();

        // Kiểm tra đã có shop chưa
        if ($user->shop()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã đăng ký gian hàng rồi. Mỗi tài khoản chỉ được có 1 gian hàng.',
                'data'    => $user->shop,
            ], 422);
        }

        $shop = Shop::create([
            'TenShop'        => $request->TenShop,
            'SCCD'           => $request->SCCD,
            'SoDienThoai'    => $request->SoDienThoai,
            'DiaChi'         => $request->DiaChi,
            'SoTaiKhoang'    => $request->SoTaiKhoang,
            'TenNganHang'    => $request->TenNganHang,
            'Tittle'         => $request->Tittle,
            'GioiThieu'      => $request->GioiThieu,
            'NgayDangKy'     => now(),
            'NgayDuyet'      => null,
            'TrangThaiDuyet' => Shop::DUYET_CHO,
            'TrangThai'      => 1,
            'ID_User'        => $user->ID_User,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký gian hàng thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $shop->load('user:ID_User,HoTen,email'),
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/seller/shop
    // Middleware: auth:sanctum
    // Xem shop của mình.
    // ─────────────────────────────────────────────────────────────────────────
    public function myShop(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng ký gian hàng.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $shop->load('user:ID_User,HoTen,email'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/seller/shop
    // Middleware: auth:sanctum
    // Cập nhật thông tin shop của mình.
    // ─────────────────────────────────────────────────────────────────────────
    public function update(UpdateShopRequest $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa có gian hàng để cập nhật.',
            ], 404);
        }

        $data = $request->safe()->except(['logo', 'baner']);

        // Upload logo nếu có
        if ($request->hasFile('logo')) {
            if ($shop->logo) {
                Storage::disk('public')->delete($shop->logo);
            }
            $data['logo'] = $request->file('logo')->store('shops/logo', 'public');
        }

        // Upload banner nếu có
        if ($request->hasFile('baner')) {
            if ($shop->baner) {
                Storage::disk('public')->delete($shop->baner);
            }
            $data['baner'] = $request->file('baner')->store('shops/baner', 'public');
        }

        $shop->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật gian hàng thành công.',
            'data'    => $shop->fresh()->load('user:ID_User,HoTen,email'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/shops
    // Middleware: auth:sanctum + role:Admin
    // Danh sách tất cả shop (có filter theo TrangThaiDuyet).
    // ─────────────────────────────────────────────────────────────────────────
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Shop::with('user:ID_User,HoTen,email,sdt')->withCount('products');

        // Lọc theo trạng thái duyệt
        if ($request->filled('trang_thai_duyet')) {
            $query->where('TrangThaiDuyet', $request->trang_thai_duyet);
        }

        // Tìm kiếm
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('TenShop', 'like', "%{$search}%")
                  ->orWhere('SoDienThoai', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($qUser) use ($search) {
                      $qUser->where('HoTen', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('sdt', 'like', "%{$search}%");
                  });
            });
        }

        $shops = $query->orderByDesc('NgayDangKy')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $shops,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/admin/shops/{id}/approve
    // Middleware: auth:sanctum + role:Admin
    // ─────────────────────────────────────────────────────────────────────────
    public function approve(int $id): JsonResponse
    {
        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng không tồn tại.',
            ], 404);
        }

        if ($shop->TrangThaiDuyet === Shop::DUYET_DA) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng này đã được duyệt trước đó.',
                'data'    => $shop,
            ], 409);
        }

        $shop->update([
            'TrangThaiDuyet' => Shop::DUYET_DA,
            'NgayDuyet'      => now(),
            'LyDoTuChoi'     => null,
        ]);

        // ── Chuyển role user sang NguoiBan (ID_role = 3) ──────────────────────
        $shop->user()->update(['ID_role' => 3]);

        return response()->json([
            'success' => true,
            'message' => "Đã duyệt gian hàng \"{$shop->TenShop}\". Tài khoản đã được nâng cấp lên Người Bán.",
            'data'    => $shop->fresh()->load('user:ID_User,HoTen,email,ID_role'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/admin/shops/{id}/reject
    // Middleware: auth:sanctum + role:Admin
    // ─────────────────────────────────────────────────────────────────────────
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'LyDoTuChoi' => 'nullable|string|max:1000',
        ]);

        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng không tồn tại.',
            ], 404);
        }

        if ($shop->TrangThaiDuyet === Shop::DUYET_TU_CHOI) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng này đã bị từ chối trước đó.',
                'data'    => $shop,
            ], 409);
        }

        $shop->update([
            'TrangThaiDuyet' => Shop::DUYET_TU_CHOI,
            'LyDoTuChoi'     => $request->input('LyDoTuChoi', $request->input('ly_do_tu_choi')),
        ]);

        // ── Nếu user đang là NguoiBan thì revert về NguoiMua (ID_role = 2) ───
        if ($shop->user && (int) $shop->user->ID_role === 3) {
            $shop->user()->update(['ID_role' => 2]);
        }

        return response()->json([
            'success' => true,
            'message' => "Đã từ chối gian hàng \"{$shop->TenShop}\". Tài khoản đã trở về Người Mua.",
            'data'    => $shop->fresh()->load('user:ID_User,HoTen,email,ID_role'),
        ]);
    }
}
