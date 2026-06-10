<?php

namespace App\Http\Controllers;

use App\Http\Requests\DonHang\StoreDonHangRequest;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonHangController extends Controller
{
    /**
     * Đặt hàng mới — chỉ NguoiMua.
     *
     * POST /api/don-hang
     * Middleware: auth:sanctum, role:NguoiMua
     *
     * Body:
     * {
     *   "DiaChiGiao": "123 Lê Lợi, Q.1, TP.HCM",
     *   "SDTNhanHang": "0901234567",
     *   "san_pham": [
     *     { "ID_SanPham": 1, "SoLuong": 2 },
     *     { "ID_SanPham": 3, "SoLuong": 1 }
     *   ]
     * }
     */
    public function store(StoreDonHangRequest $request): JsonResponse
    {
        // ── Kiểm tra tồn kho trước khi tạo đơn ──────────────────────────────
        $sanPhamIds   = collect($request->san_pham)->pluck('ID_SanPham');
        $sanPhamList  = Product::whereIn('ID_SanPham', $sanPhamIds)
                               ->where('TrangThai', 1)
                               ->get()
                               ->keyBy('ID_SanPham');

        $loi = [];
        foreach ($request->san_pham as $item) {
            $sp = $sanPhamList->get($item['ID_SanPham']);

            if (! $sp) {
                $loi[] = "Sản phẩm ID {$item['ID_SanPham']} không tồn tại hoặc đã ngừng bán.";
                continue;
            }

            if (! $sp->conDuTonKho($item['SoLuong'])) {
                $loi[] = "Sản phẩm \"{$sp->TenSanPham}\" chỉ còn {$sp->SoLuongTon} sản phẩm, "
                       . "bạn đặt {$item['SoLuong']}.";
            }
        }

        if (! empty($loi)) {
            return response()->json([
                'success' => false,
                'message' => 'Không đủ hàng trong kho.',
                'errors'  => $loi,
            ], 422);
        }

        // ── Tạo đơn hàng trong transaction ───────────────────────────────────
        DB::beginTransaction();
        try {
            // Tính tổng tiền
            $tongTien = 0;
            foreach ($request->san_pham as $item) {
                $tongTien += $sanPhamList[$item['ID_SanPham']]->Gia * $item['SoLuong'];
            }

            // Tạo đơn hàng
            $donHang = DonHang::create([
                'ID_User'     => $request->user()->ID_User,
                'DiaChiGiao'  => $request->DiaChiGiao,
                'SDTNhanHang' => $request->SDTNhanHang,
                'TongTien'    => $tongTien,
                'TrangThai'   => DonHang::TRANG_THAI_CHO_XAC_NHAN,
                'NgayDat'     => now(),
            ]);

            // Tạo chi tiết đơn hàng + trừ tồn kho
            foreach ($request->san_pham as $item) {
                $sp = $sanPhamList[$item['ID_SanPham']];

                ChiTietDonHang::create([
                    'ID_DonHang' => $donHang->ID_DonHang,
                    'ID_SanPham' => $sp->ID_SanPham,
                    'SoLuong'    => $item['SoLuong'],
                    'DonGia'     => $sp->Gia,          // Lưu giá tại thời điểm đặt hàng
                ]);

                // Trừ tồn kho
                $sp->decrement('SoLuongTon', $item['SoLuong']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đặt hàng thành công.',
                'data'    => $donHang->load('chiTiet.sanPham'),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Danh sách đơn hàng của user đang đăng nhập.
     * GET /api/don-hang
     * Middleware: auth:sanctum
     */
    public function index(Request $request): JsonResponse
    {
        $donHang = DonHang::with('chiTiet.sanPham')
            ->where('ID_User', $request->user()->ID_User)
            ->orderByDesc('ID_DonHang')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $donHang,
        ]);
    }

    /**
     * Chi tiết 1 đơn hàng (chỉ chủ đơn mới xem được).
     * GET /api/don-hang/{id}
     * Middleware: auth:sanctum
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $donHang = DonHang::with('chiTiet.sanPham')
            ->where('ID_DonHang', $id)
            ->where('ID_User', $request->user()->ID_User)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $donHang,
        ]);
    }
}
