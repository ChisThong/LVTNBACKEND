<?php

namespace App\Http\Controllers;

use App\Http\Requests\SanPham\StoreSanPhamRequest;
use App\Http\Requests\SanPham\UpdateSanPhamRequest;
use App\Models\SanPham;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SanPhamController extends Controller
{
    /**
     * Danh sách sản phẩm (public).
     * GET /api/san-pham
     */
    public function index(Request $request): JsonResponse
    {
        $sanPham = SanPham::with('nguoiBan:ID_User,HoTen')
            ->where('TrangThai', 1)
            ->when($request->search, fn($q) => $q->where('TenSP', 'like', "%{$request->search}%"))
            ->when($request->gia_min, fn($q) => $q->where('Gia', '>=', $request->gia_min))
            ->when($request->gia_max, fn($q) => $q->where('Gia', '<=', $request->gia_max))
            ->orderByDesc('ID_SanPham')
            ->paginate(12);

        return response()->json([
            'success' => true,
            'data'    => $sanPham,
        ]);
    }

    /**
     * Chi tiết 1 sản phẩm (public).
     * GET /api/san-pham/{id}
     */
    public function show(int $id): JsonResponse
    {
        $sanPham = SanPham::with('nguoiBan:ID_User,HoTen')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $sanPham,
        ]);
    }

    /**
     * Tạo sản phẩm mới — chỉ NguoiBan.
     * POST /api/san-pham
     * Middleware: auth:sanctum, role:NguoiBan
     */
    public function store(StoreSanPhamRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['ID_User']   = $request->user()->ID_User;
        $data['TrangThai'] = 1;
        $data['NgayTao']   = now();

        // Xử lý upload hình ảnh
        if ($request->hasFile('HinhAnh')) {
            $data['HinhAnh'] = $request->file('HinhAnh')
                ->store('san-pham', 'public');
        }

        $sanPham = SanPham::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tạo sản phẩm thành công.',
            'data'    => $sanPham,
        ], 201);
    }

    /**
     * Cập nhật sản phẩm — chỉ NguoiBan sở hữu.
     * PUT /api/san-pham/{id}
     * Middleware: auth:sanctum, role:NguoiBan
     */
    public function update(UpdateSanPhamRequest $request, int $id): JsonResponse
    {
        $sanPham = SanPham::where('ID_SanPham', $id)
            ->where('ID_User', $request->user()->ID_User)
            ->firstOrFail();

        $data = $request->validated();

        // Xử lý upload hình ảnh mới
        if ($request->hasFile('HinhAnh')) {
            // Xoá ảnh cũ nếu có
            if ($sanPham->HinhAnh) {
                Storage::disk('public')->delete($sanPham->HinhAnh);
            }
            $data['HinhAnh'] = $request->file('HinhAnh')
                ->store('san-pham', 'public');
        }

        $sanPham->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật sản phẩm thành công.',
            'data'    => $sanPham->fresh(),
        ]);
    }

    /**
     * Xoá sản phẩm — chỉ NguoiBan sở hữu.
     * DELETE /api/san-pham/{id}
     * Middleware: auth:sanctum, role:NguoiBan
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $sanPham = SanPham::where('ID_SanPham', $id)
            ->where('ID_User', $request->user()->ID_User)
            ->firstOrFail();

        if ($sanPham->HinhAnh) {
            Storage::disk('public')->delete($sanPham->HinhAnh);
        }

        $sanPham->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá sản phẩm thành công.',
        ]);
    }
}
