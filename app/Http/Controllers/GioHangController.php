<?php

namespace App\Http\Controllers;

use App\Models\GioHang;
use App\Models\ChiTietGioHang;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GioHangController extends Controller
{
    /**
     * GET /api/cart
     * Lấy giỏ hàng của user hiện tại (kèm thông tin sản phẩm + ảnh).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $gioHang = GioHang::where('ID_User', $user->ID_User)->first();

        if (!$gioHang) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $chiTiet = ChiTietGioHang::with(['sanPham.hinhAnh', 'sanPham.shop'])
            ->where('ID_GioHang', $gioHang->ID_GioHang)
            ->get()
            ->map(function ($ct) {
                $sp = $ct->sanPham;
                if (!$sp) return null;

                return [
                    'ID_ChiTietGioHang' => $ct->ID_ChiTietGioHang,
                    'ID_SanPham'        => $sp->ID_SanPham,
                    'TenSanPham'        => $sp->TenSanPham,
                    'Gia'               => (float) $sp->Gia,
                    'GiaSP'             => (float) $ct->GiaSP,
                    'SoLuong'           => $ct->SoLuong,
                    'SoLuongTon'        => $sp->SoLuongTon,
                    'HinhAnh'           => $sp->hinhAnh->first()?->HinhAnh ?? null,
                    'ID_Shop'           => $sp->ID_Shop,
                    'TenShop'           => $sp->shop?->TenShop ?? 'Gian hàng đặc sản',
                    'TrangThai'         => $sp->TrangThai,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['success' => true, 'data' => $chiTiet]);
    }

    /**
     * POST /api/cart
     * Thêm sản phẩm vào giỏ (nếu đã có thì cộng thêm SoLuong).
     * Body: { ID_SanPham, SoLuong }
     */
    public function addOrUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'ID_SanPham' => 'required|integer|exists:sanpham,ID_SanPham',
            'SoLuong'    => 'required|integer|min:1',
        ]);

        $user   = $request->user();
        $idSP   = $request->ID_SanPham;
        $soLuong = (int) $request->SoLuong;

        $sp = Product::find($idSP);
        if (!$sp || $sp->TrangThai != 1) {
            return response()->json(['success' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng bán.'], 422);
        }

        DB::beginTransaction();
        try {
            // Lấy hoặc tạo giỏ hàng
            $gioHang = GioHang::firstOrCreate(
                ['ID_User' => $user->ID_User],
                ['ngay_tao' => now()]
            );

            // Kiểm tra sản phẩm đã trong giỏ chưa
            $chiTiet = ChiTietGioHang::where('ID_GioHang', $gioHang->ID_GioHang)
                ->where('ID_SanPham', $idSP)
                ->first();

            if ($chiTiet) {
                $newQty = $chiTiet->SoLuong + $soLuong;
                // Kiểm tra tồn kho
                if ($newQty > $sp->SoLuongTon) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Chỉ còn {$sp->SoLuongTon} sản phẩm trong kho.",
                    ], 422);
                }
                $chiTiet->update(['SoLuong' => $newQty, 'GiaSP' => $sp->Gia]);
            } else {
                if ($soLuong > $sp->SoLuongTon) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Chỉ còn {$sp->SoLuongTon} sản phẩm trong kho.",
                    ], 422);
                }
                ChiTietGioHang::create([
                    'ID_GioHang' => $gioHang->ID_GioHang,
                    'ID_SanPham' => $idSP,
                    'SoLuong'    => $soLuong,
                    'GiaSP'      => $sp->Gia,
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Đã thêm vào giỏ hàng.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/cart/{idSanPham}
     * Cập nhật số lượng sản phẩm trong giỏ.
     * Body: { SoLuong }
     */
    public function update(Request $request, $idSanPham): JsonResponse
    {
        $request->validate(['SoLuong' => 'required|integer|min:1']);

        $user    = $request->user();
        $soLuong = (int) $request->SoLuong;

        $gioHang = GioHang::where('ID_User', $user->ID_User)->first();
        if (!$gioHang) {
            return response()->json(['success' => false, 'message' => 'Giỏ hàng không tồn tại.'], 404);
        }

        $chiTiet = ChiTietGioHang::where('ID_GioHang', $gioHang->ID_GioHang)
            ->where('ID_SanPham', $idSanPham)
            ->first();

        if (!$chiTiet) {
            return response()->json(['success' => false, 'message' => 'Sản phẩm không có trong giỏ hàng.'], 404);
        }

        $sp = Product::find($idSanPham);
        if ($soLuong > $sp->SoLuongTon) {
            return response()->json([
                'success' => false,
                'message' => "Chỉ còn {$sp->SoLuongTon} sản phẩm trong kho.",
            ], 422);
        }

        $chiTiet->update(['SoLuong' => $soLuong, 'GiaSP' => $sp->Gia]);

        return response()->json(['success' => true, 'message' => 'Đã cập nhật số lượng.']);
    }

    /**
     * DELETE /api/cart/{idSanPham}
     * Xóa 1 sản phẩm khỏi giỏ hàng.
     */
    public function remove(Request $request, $idSanPham): JsonResponse
    {
        $user = $request->user();

        $gioHang = GioHang::where('ID_User', $user->ID_User)->first();
        if (!$gioHang) {
            return response()->json(['success' => true, 'message' => 'Giỏ hàng trống.']);
        }

        ChiTietGioHang::where('ID_GioHang', $gioHang->ID_GioHang)
            ->where('ID_SanPham', $idSanPham)
            ->delete();

        return response()->json(['success' => true, 'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.']);
    }

    /**
     * DELETE /api/cart
     * Xóa toàn bộ giỏ hàng (dùng sau khi đặt hàng thành công).
     */
    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        $gioHang = GioHang::where('ID_User', $user->ID_User)->first();
        if ($gioHang) {
            ChiTietGioHang::where('ID_GioHang', $gioHang->ID_GioHang)->delete();
        }

        return response()->json(['success' => true, 'message' => 'Đã xóa giỏ hàng.']);
    }

    /**
     * POST /api/cart/sync
     * Đồng bộ giỏ hàng từ localStorage vào server (dùng khi vừa đăng nhập).
     * Body: { items: [{ ID_SanPham, SoLuong }] }
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'items'              => 'required|array',
            'items.*.ID_SanPham' => 'required|integer',
            'items.*.SoLuong'    => 'required|integer|min:1',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $gioHang = GioHang::firstOrCreate(
                ['ID_User' => $user->ID_User],
                ['ngay_tao' => now()]
            );

            foreach ($request->items as $item) {
                $sp = Product::find($item['ID_SanPham']);
                if (!$sp || $sp->TrangThai != 1) continue;

                $chiTiet = ChiTietGioHang::where('ID_GioHang', $gioHang->ID_GioHang)
                    ->where('ID_SanPham', $item['ID_SanPham'])
                    ->first();

                $soLuong = min((int)$item['SoLuong'], $sp->SoLuongTon);
                if ($soLuong <= 0) continue;

                if ($chiTiet) {
                    // Merge: lấy max(server, local) nhưng không vượt tồn kho
                    $newQty = min($chiTiet->SoLuong + $soLuong, $sp->SoLuongTon);
                    $chiTiet->update(['SoLuong' => $newQty, 'GiaSP' => $sp->Gia]);
                } else {
                    ChiTietGioHang::create([
                        'ID_GioHang' => $gioHang->ID_GioHang,
                        'ID_SanPham' => $item['ID_SanPham'],
                        'SoLuong'    => $soLuong,
                        'GiaSP'      => $sp->Gia,
                    ]);
                }
            }

            DB::commit();

            // Trả về giỏ hàng đã merge
            return $this->index($request);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi đồng bộ giỏ hàng: ' . $e->getMessage()], 500);
        }
    }
}
