<?php

namespace App\Http\Controllers;

use App\Models\PhanLoaiSP;
use Illuminate\Http\JsonResponse;

class PhanLoaiController extends Controller
{
    /**
     * GET /api/phan-loai
     * Public — Lấy toàn bộ danh mục sản phẩm.
     */
    public function index(): JsonResponse
    {
        $danhSach = PhanLoaiSP::orderBy('TenLoai')->get();

        return response()->json([
            'success' => true,
            'data'    => $danhSach,
        ]);
    }

    /**
     * GET /api/phan-loai/{id}
     * Public — Chi tiết 1 danh mục + danh sách sản phẩm.
     */
    public function show(int $id): JsonResponse
    {
        $phanLoai = PhanLoaiSP::with(['products' => function ($q) {
            $q->where('TrangThai', 1)->with('hinhAnh');
        }])->find($id);

        if (! $phanLoai) {
            return response()->json([
                'success' => false,
                'message' => 'Danh mục không tồn tại.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $phanLoai,
        ]);
    }
}
