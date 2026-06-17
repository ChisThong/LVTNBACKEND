<?php

namespace App\Http\Controllers;

use App\Models\DanhGia;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\PhanHoiDanhGia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Exception;

class DanhGiaController extends Controller
{
    public function guiDanhGia(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'ID_ChiTiet' => 'required|integer',
                'ID_SanPham' => 'required|integer',
                'XepLoai'    => 'required|integer|between:1,5',
                'BinhLuan'   => 'nullable|string|max:1000',
                'HinhAnh'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ], [
                'XepLoai.between' => 'Số sao xếp loại phải nằm trong khoảng từ 1 đến 5.',
                'HinhAnh.image'   => 'File tải lên phải là định dạng hình ảnh.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors()
                ], 422);
            }
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn vui lòng đăng nhập để thực hiện đánh giá.'
                ], 401);
            }
            $chiTietDonHang = ChiTietDonHang::with('donHang')
                ->where('ID_ChiTiet', $request->input('ID_ChiTiet'))
                ->where('ID_SanPham', $request->input('ID_SanPham'))
                ->first();

            if (!$chiTietDonHang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thông tin sản phẩm hoặc chi tiết đơn hàng không hợp lệ.'
                ], 400);
            }

            $donHang = $chiTietDonHang->donHang;

            if ($donHang->ID_User != $userId || $donHang->TrangThai != DonHang::TRANG_THAI_HOAN_TAT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chỉ được đánh giá những sản phẩm thuộc đơn hàng đã giao thành công.'
                ], 403);
            }

            $daDanhGia = DanhGia::where('ID_User', $userId)
                ->where('ID_ChiTiet', $request->input('ID_ChiTiet'))
                ->exists();

            if ($daDanhGia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã gửi đánh giá cho sản phẩm thuộc đơn hàng này rồi.'
                ], 400);
            }
            $pathHinhAnh = null;
            if ($request->hasFile('HinhAnh')) {
                $file = $request->file('HinhAnh');
                $fileName = 'danhgia_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/danhgia'), $fileName);
                $pathHinhAnh = 'uploads/danhgia/' . $fileName;
            }
            $danhGiaMoi = DanhGia::create([
                'ID_User'     => $userId,
                'ID_SanPham'  => $request->input('ID_SanPham'),
                'ID_ChiTiet'  => $request->input('ID_ChiTiet'),
                'XepLoai'     => (int) $request->input('XepLoai'),
                'BinhLuan'    => $request->input('BinhLuan'),
                'HinhAnh'     => $pathHinhAnh,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cảm ơn bạn đã gửi đánh giá sản phẩm thành công!',
                'data'    => $danhGiaMoi
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi gửi đánh giá.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    public function index(String $id)
    {
        try {
            $danhgias = DanhGia::with(['user:ID_User,HoTen', 'phanHoi'])->where('ID_SanPham', $id)->get();
            return response()->json([
                'success' => true,
                'data' => $danhgias
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
    public function phanhoi(Request $request, String $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'NoiDungPhanHoi' => 'required|string|max:1000'
            ], [
                'NoiDungPhanHoi.required' => 'Vui lòng nhập nội dung phản hồi.',
                'NoiDungPhanHoi.max'      => 'Nội dung phản hồi không được vượt quá 1000 ký tự.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors'  => $validator->errors()
                ], 422);
            }

            $danhGia = DanhGia::find($id);
            if (!$danhGia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy bài đánh giá này để phản hồi.'
                ], 404);
            }
            if ($danhGia->phanHoi()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn đã phản hồi đánh giá này rồi. Để sửa đổi, hãy dùng tính năng cập nhật.'
                ], 400);
            }
            $phanHoiMoi = PhanHoiDanhGia::create([
                'ID_DanhGia'     => (int) $id,
                'NoiDungPhanHoi' => $request->input('NoiDungPhanHoi'),
                'NgayPhanHoi'    => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gửi phản hồi đánh giá thành công!',
                'data'    => $phanHoiMoi
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống khi gửi phản hồi.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
