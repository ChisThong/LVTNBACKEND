<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDonHangController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DonHang::with(['donHangTong.user', 'shop', 'chiTiet.sanPham']);
            if ($request->filled('search')) {
                $search = $request->input('search');

                $query->where(function ($mainQuery) use ($search) {
                    $mainQuery->where('MaDonHangCon', 'like', '%' . $search . '%')
                        ->orWhereHas('donHangTong.user', function ($q) use ($search) {
                            $q->where('HoTen', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('shop', function ($q) use ($search) {
                            $q->where('TenShop', 'like', '%' . $search . '%');
                        });
                });
            }
            if ($request->filled('TrangThai')) {
                $query->where('TrangThai', (int) $request->input('TrangThai'));
            }
            if ($request->filled('TuNgay') && $request->filled('DenNgay')) {
                $tuNgay = $request->input('TuNgay') . ' 00:00:00';
                $denNgay = $request->input('DenNgay') . ' 23:59:59'; 

                $query->whereBetween('date', [$tuNgay, $denNgay]);
            } elseif ($request->filled('TuNgay')) {
                $query->where('date', '>=', $request->input('TuNgay') . ' 00:00:00');
            } elseif ($request->filled('DenNgay')) {
                $query->where('date', '<=', $request->input('DenNgay') . ' 23:59:59');
            }
            $data = $query->orderBy('ID_DonHang', 'desc')->paginate(10);
            $countdanggiao = DonHang::where('TrangThai', 2)->count();
            $countdagiao = DonHang::where('TrangThai', 3)->count();
            $counthuy = DonHang::where('TrangThai', 4)->count();
            $totals = DonHang::count();

            return response()->json([
                'success' => true,
                'data' => $data,
                'demdanggiao' => $countdanggiao,
                'demhoantat' => $countdagiao,
                'demhuy' => $counthuy,
                'tongdon' => $totals
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
    public function chitiet(string $id): JsonResponse
    {
        try {
            $donHang = DonHang::with(['donHangTong.user', 'shop', 'chiTiet.sanPham'])
                ->find((int) $id);
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

    /**
     * Cập nhật trạng thái đơn hàng & Xử lý ví tiền (nếu hoàn tất)
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'TrangThai' => 'required|integer|in:0,1,2,3,4' // 3 = HOAN_TAT
        ]);

        $newStatus = (int) $request->TrangThai;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Khóa bi quan dòng đơn hàng để tránh update đồng thời
            $donHang = DonHang::lockForUpdate()->find($id);

            if (!$donHang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng'
                ], 404);
            }

            // Nếu đơn hàng đã hoàn tất trước đó thì không xử lý lại tiền
            if ($donHang->TrangThai === DonHang::TRANG_THAI_HOAN_TAT && $newStatus === DonHang::TRANG_THAI_HOAN_TAT) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng này đã được hoàn tất trước đó.'
                ], 400);
            }

            // Cập nhật trạng thái mới
            $donHang->TrangThai = $newStatus;
            $donHang->save();

            // KÍCH HOẠT LUỒNG VÍ ĐIỆN TỬ KHI HOÀN TẤT
            if ($newStatus === DonHang::TRANG_THAI_HOAN_TAT) {
                $tongGia = $donHang->TongGia;
                $commission = $tongGia * 0.05; // Hoa hồng 5%
                $sellerAmount = $tongGia - $commission; // 95% cho Seller

                $adminUserId = 6;
                $sellerUserId = $donHang->shop->ID_User;

                // 1. Chuyển 95% tiền cho Seller
                $sellerWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $sellerUserId]);
                $sellerWallet = \App\Models\Wallet::lockForUpdate()->find($sellerWallet->id);

                $beforeSeller = $sellerWallet->balance;
                $sellerWallet->balance += $sellerAmount;
                $sellerWallet->save();

                \App\Models\WalletTransaction::create([
                    'wallet_id'      => $sellerWallet->id,
                    'type'           => 'release',
                    'status'         => 'success',
                    'amount'         => $sellerAmount,
                    'balance_before' => $beforeSeller,
                    'balance_after'  => $sellerWallet->balance,
                    'reference_type' => 'donhang',
                    'reference_id'   => $donHang->ID_DonHang
                ]);

                // 2. Chuyển 5% hoa hồng cho Admin
                $adminWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $adminUserId]);
                $adminWallet = \App\Models\Wallet::lockForUpdate()->find($adminWallet->id);

                $beforeAdmin = $adminWallet->balance;
                $adminWallet->balance += $commission;
                $adminWallet->save();

                \App\Models\WalletTransaction::create([
                    'wallet_id'      => $adminWallet->id,
                    'type'           => 'commission',
                    'status'         => 'success',
                    'amount'         => $commission,
                    'balance_before' => $beforeAdmin,
                    'balance_after'  => $adminWallet->balance,
                    'reference_type' => 'donhang',
                    'reference_id'   => $donHang->ID_DonHang
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái đơn hàng thành công.',
                'data'    => $donHang
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }
}
