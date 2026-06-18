<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDonHangController extends Controller
{
    public function index(Request $request):JsonResponse{
        try{
           $query = DonHang::with(['donHangTong.user', 'shop', 'chiTiet.sanPham']);

            // 1. Tìm kiếm theo TÊN KHÁCH HÀNG (Đi xuyên qua bảng donhangtong -> user)
            if ($request->filled('TenKhachHang')) {
                $tenKhach = $request->input('TenKhachHang');
                $query->whereHas('donHangTong.user', function ($q) use ($tenKhach) {
                    $q->where('HoTen', 'like', '%' . $tenKhach . '%');
                });
            }

            // 2. Tìm kiếm theo TÊN SHOP (Đi xuyên qua bảng shop)
            if ($request->filled('TenShop')) {
                $tenShop = $request->input('TenShop');
                $query->whereHas('shop', function ($q) use ($tenShop) {
                    $q->where('TenShop', 'like', '%' . $tenShop . '%');
                });
            }
            if ($request->filled('MaDonHangCon')) {
                $query->where('MaDonHangCon', 'like', '%' . $request->input('MaDonHangCon') . '%');
            }

            // 4. Lọc theo TRẠNG THÁI ĐƠN HÀNG (Kiểu số TINYINT: 0, 1, 2, 3)
            if ($request->filled('TrangThai')) {
                $query->where('TrangThai', (int) $request->input('TrangThai'));
            }
                        if ($request->filled('TuNgay') && $request->filled('DenNgay')) {
                $tuNgay = $request->input('TuNgay') . ' 00:00:00'; // Bắt đầu từ 0h00 phút của ngày đó
                $denNgay = $request->input('DenNgay') . ' 23:59:59'; // Kết thúc vào 23h59 phút của ngày đó

                $query->whereBetween('date', [$tuNgay, $denNgay]);
            } elseif ($request->filled('TuNgay')) {
                // Nếu chỉ nhập Từ Ngày (Lấy tất cả đơn từ ngày đó đến hiện tại)
                $query->where('date', '>=', $request->input('TuNgay') . ' 00:00:00');
            } elseif ($request->filled('DenNgay')) {
                // Nếu chỉ nhập Đến Ngày (Lấy tất cả đơn từ quá khứ đến ngày đó)
                $query->where('date', '<=', $request->input('DenNgay') . ' 23:59:59');
            }
            $data = $query->orderBy('ID_DonHang', 'desc')->paginate(10);
            $countdanggiao=DonHang::where('TrangThai',2)->count();
            $countdagiao=DonHang::where('TrangThai',3)->count();
            $counthuy=DonHang::where('TrangThai',4)->count();
            $totals=DonHang::count();

            return response()->json([
                'success'=> true,
                'data'=> $data,
                'demdanggiao'=>$countdanggiao,
                'demhoantat'=>$countdagiao,
                'demhuy'=>$counthuy,
                'tongdon'=>$totals
            ],200);
        }catch(\Exception $e){
            return response()->json([
                'success'=>false,
                'message'=>'Lỗi hệ thống: '.$e->getMessage()
            ],500);
        }
    }
   public function chitiet(string $id): JsonResponse
    {
        try {
            // Nạp đầy đủ thông tin cha (donHangTong), thông tin shop, và mảng con (chiTiet sản phẩm)
            $donHang = DonHang::with(['donHangTong.user', 'shop', 'chiTiet.sanPham'])
                ->find((int) $id);

            // Kiểm tra nếu đơn hàng không tồn tại trong database
            if (!$donHang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy thông tin đơn hàng này.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết đơn hàng thành công.',
                'data'    => $donHang
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
}
