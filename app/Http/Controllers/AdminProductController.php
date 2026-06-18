<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    private array $with = ['shop.user', 'phanLoai', 'hinhAnh', 'tinhThanh'];

    /**
     * Lấy danh sách toàn bộ sản phẩm (dành cho Admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with($this->with);

        // Lọc theo từ khóa tìm kiếm
        if ($request->filled('search')) {
            $query->where('TenSanPham', 'like', '%' . $request->search . '%');
        }

        // Lọc theo trạng thái hiển thị
        if ($request->filled('trang_thai')) {
            $query->where('TrangThai', $request->trang_thai);
        }

        // Lọc theo trạng thái duyệt
        if ($request->filled('trang_thai_duyet')) {
            $query->where('TrangThaiDuyet', $request->trang_thai_duyet);
        }

        // Lọc theo trạng thái hiển thị (Admin visibility)
        if ($request->filled('trang_thai_hien_thi')) {
            $query->where('TrangThaiHienThi', $request->trang_thai_hien_thi);
        }

        // Lọc theo ID gian hàng (hỗ trợ cả ID_Shop hoặc id_shop)
        if ($request->filled('ID_Shop')) {
            $query->where('ID_Shop', $request->ID_Shop);
        }
        if ($request->filled('id_shop')) {
            $query->where('ID_Shop', $request->input('id_shop'));
        }

        // Lọc theo danh mục
        if ($request->filled('ID_PhanLoai')) {
            $query->where('ID_PhanLoai', $request->ID_PhanLoai);
        }

        // Sắp xếp
        $sortBy  = in_array($request->sort_by, ['Gia', 'TenSanPham', 'ID_SanPham', 'TrangThai', 'SoLuongTon'])
                   ? $request->sort_by : 'ID_SanPham';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = (int) ($request->per_page ?? 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách sản phẩm toàn hệ thống.',
            'data'    => $products,
        ]);
    }

    /**
     * Lấy chi tiết một sản phẩm
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with($this->with)->find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chi tiết sản phẩm.',
            'data'    => $product,
        ]);
    }

    /**
     * Ẩn / Khóa sản phẩm (Ngừng bán)
     */
    public function hide(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        $lyDoAn = $request->input('LyDoAn');

        $product->update([
            'TrangThai'        => 0, // Product::TRANG_THAI_AN
            'LyDoAn'           => $lyDoAn,
            'TrangThaiHienThi' => Product::HIEN_THI_AN,
            'LyDoAdminAn'      => $lyDoAn,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sản phẩm đã bị ẩn/ngừng bán.',
            'data'    => $product->fresh($this->with),
        ]);
    }

    /**
     * Khôi phục / Cho phép bán lại sản phẩm
     */
    public function restore(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        $product->update([
            'TrangThai'        => 1, // Product::TRANG_THAI_HIEN
            'LyDoAn'           => null,
            'TrangThaiHienThi' => Product::HIEN_THI_HIEN,
            'LyDoAdminAn'      => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sản phẩm đã được khôi phục trạng thái bán.',
            'data'    => $product->fresh($this->with),
        ]);
    }

    /**
     * Toggle hiển thị sản phẩm (Admin ẩn/hiện độc lập với Seller)
     * PATCH /api/admin/products/{id}/toggle-visibility
     */
    public function toggleVisibility(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        $request->validate([
            'LyDoAdminAn' => 'nullable|string|max:1000',
        ]);

        $isCurrentlyVisible = $product->TrangThaiHienThi === Product::HIEN_THI_HIEN;
        $newVisibility      = $isCurrentlyVisible ? Product::HIEN_THI_AN : Product::HIEN_THI_HIEN;

        $updateData = ['TrangThaiHienThi' => $newVisibility];

        if ($newVisibility === Product::HIEN_THI_AN) {
            // Ẩn: lưu lý do (nếu có)
            $updateData['LyDoAdminAn'] = $request->input('LyDoAdminAn');
        } else {
            // Hiện lại: xóa lý do
            $updateData['LyDoAdminAn'] = null;
        }

        $product->update($updateData);

        return response()->json([
            'success'    => true,
            'message'    => $newVisibility === Product::HIEN_THI_AN
                            ? 'Admin đã ẩn sản phẩm khỏi website.'
                            : 'Admin đã hiện lại sản phẩm.',
            'visibility' => $newVisibility,
            'data'       => $product->fresh($this->with),
        ]);
    }

    /**
     * Duyệt sản phẩm
     */
    public function approve(int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        $product->update([
            'TrangThaiDuyet' => Product::DUYET_DA,
            'NgayDuyet' => now(),
            'LyDoTuChoi' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã duyệt sản phẩm thành công.',
            'data'    => $product->fresh($this->with),
        ]);
    }

    /**
     * Từ chối sản phẩm
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại.',
            ], 404);
        }

        $lyDo = $request->input('LyDoTuChoi');

        $product->update([
            'TrangThaiDuyet' => Product::DUYET_TU_CHOI,
            'LyDoTuChoi' => $lyDo
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối sản phẩm.',
            'data'    => $product->fresh($this->with),
        ]);
    }
}
