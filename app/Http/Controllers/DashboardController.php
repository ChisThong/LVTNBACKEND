<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\DonHang;
use Carbon\Carbon;

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
        try {
            $user = $request->user()->load('role');
            $dauHomNay = \Carbon\Carbon::now()->startOfDay();
            $ngayHomNay = \Carbon\Carbon::now();

            $dauHomQua = \Carbon\Carbon::yesterday()->startOfDay();
            $hetHomQua = \Carbon\Carbon::yesterday()->endOfDay();

            $tinhPhanTram = function ($homnay, $homqua) {
                if ($homqua == 0) {
                    return $homnay > 0 ? 100.0 : 0.0;
                }
                return round((($homnay - $homqua) / $homqua) * 100, 2);
            };

            // --- 1. DOANH THU ---
            $tongDThomNay = (float) \App\Models\DonHang::where('TrangThai', 3)->betweenDate($dauHomNay, $ngayHomNay)->sum('TongGia');
            $tongDThomQua = (float) \App\Models\DonHang::where('TrangThai', 3)->betweenDate($dauHomQua, $hetHomQua)->sum('TongGia');
            $ptDTHomNay   = $tinhPhanTram($tongDThomNay, $tongDThomQua);

            // --- 2. USER MỚI ---
            $userHomNay = \App\Models\User::where('ID_role', 2)->betweenDate($dauHomNay, $ngayHomNay)->count();
            $userHomQua = \App\Models\User::where('ID_role', 2)->betweenDate($dauHomQua, $hetHomQua)->count();
            $ptUserHomNay = $tinhPhanTram($userHomNay, $userHomQua);

            // --- 3. ĐƠN HÀNG MỚI ---
            $donHangHomNay = \App\Models\DonHang::betweenDate($dauHomNay, $ngayHomNay)->count();
            $donHangHomQua = \App\Models\DonHang::betweenDate($dauHomQua, $hetHomQua)->count();
            $ptDonHangHomNay = $tinhPhanTram($donHangHomNay, $donHangHomQua);

            // --- 4. GIAN HÀNG ĐĂNG KÝ MỚI ---
            $gianHangHomNay = \App\Models\Shop::betweenDate($dauHomNay, $ngayHomNay)->count();
            $gianHangHomQua = \App\Models\Shop::betweenDate($dauHomQua, $hetHomQua)->count();
            $ptGianHangHomNay = $tinhPhanTram($gianHangHomNay, $gianHangHomQua);

            // --- 5. TỔNG SỐ GIAN HÀNG ĐANG CHỜ DUYỆT ---
            $shopChoDuyetTong = \App\Models\Shop::where('TrangThaiDuyet', 'cho_duyet')->count();

            // Nhận tham số từ React gửi lên, mặc định nếu không truyền gì sẽ là '7_ngay'
            $loaiBieuDo = $request->input('loai_bieu_do', '7_ngay');
            $duLieuBieuDo = [];

            if ($loaiBieuDo === '6_thang') {
                // --- KHỐI XỬ LÝ THEO 6 THÁNG GẦN ĐÂY ---
                $tuNgay = \Carbon\Carbon::now()->subMonths(5)->startOfMonth(); // Lấy từ đầu tháng của 5 tháng trước (Tổng là 6 tháng)
                $denNgay = \Carbon\Carbon::now()->endOfMonth();

                // Query gom nhóm doanh thu theo từng Tháng-Năm
                $rawChartData = \App\Models\DonHang::where('TrangThai', 3) // Chỉ tính đơn hoàn tất
                    ->whereBetween('date', [$tuNgay, $denNgay])
                    ->selectRaw("DATE_FORMAT(date, '%m-%Y') as thoi_gian")
                    ->selectRaw("SUM(TongGia) as doanh_thu")
                    ->selectRaw("COUNT(ID_DonHang) as so_don")
                    ->groupBy(DB::raw("DATE_FORMAT(date, '%m-%Y')"))
                    ->orderBy('date', 'ASC')
                    ->get()
                    ->keyBy('thoi_gian'); // Biến mảng thành key-value để dễ điền khuyết

                // Vòng lặp chạy qua 6 tháng để tự động điền khuyết số 0 nếu tháng đó không có doanh thu
                $thangChay = $tuNgay->copy();
                while ($thangChay->lte($denNgay)) {
                    $key = $thangChay->format('m-Y');

                    $duLieuBieuDo[] = [
                        'nhan' => "Tháng " . $thangChay->format('m/Y'), // Nhãn hiển thị trục X trên biểu đồ
                        'doanh_thu' => isset($rawChartData[$key]) ? (float)$rawChartData[$key]->doanh_thu : 0.0,
                        'so_don' => isset($rawChartData[$key]) ? (int)$rawChartData[$key]->so_don : 0
                    ];

                    $thangChay->addMonth(); // Tăng lên 1 tháng
                }
            } else {
                // --- KHỐI XỬ LÝ THEO 7 NGÀY QUA (MẶC ĐỊNH) ---
                $tuNgay = \Carbon\Carbon::now()->subDays(6)->startOfDay(); // Lấy từ 6 ngày trước đến nay (Tổng là 7 ngày)
                $denNgay = \Carbon\Carbon::now()->endOfDay();

                // Query gom nhóm doanh thu theo từng Ngày
                $rawChartData = \App\Models\DonHang::where('TrangThai', 3)
                    ->whereBetween('date', [$tuNgay, $denNgay])
                    ->selectRaw("DATE(date) as thoi_gian")
                    ->selectRaw("SUM(TongGia) as doanh_thu")
                    ->selectRaw("COUNT(ID_DonHang) as so_don")
                    ->groupBy(DB::raw("DATE(date)"))
                    ->orderBy('thoi_gian', 'ASC')
                    ->get()
                    ->keyBy('thoi_gian');

                // Vòng lặp chạy qua 7 ngày để điền khuyết số 0
                $ngayChay = $tuNgay->copy();
                while ($ngayChay->lte($denNgay)) {
                    $key = $ngayChay->format('Y-m-d');

                    $duLieuBieuDo[] = [
                        'nhan' => $ngayChay->format('d/m'), // Ví dụ nhãn: "26/06"
                        'doanh_thu' => isset($rawChartData[$key]) ? (float)$rawChartData[$key]->doanh_thu : 0.0,
                        'so_don' => isset($rawChartData[$key]) ? (int)$rawChartData[$key]->so_don : 0
                    ];

                    $ngayChay->addDay(); // Tăng lên 1 ngày
                }
            }

            //Top sản phẩm bán chạy trong tháng này (luôn chạy để có dữ liệu gửi về)
            $dauthang = Carbon::now()->startOfMonth();
            $hientai = Carbon::now();
            $topProducts = Product::query()
                ->withSum([
                    'chiTietDonHang as tong_ban' => function ($query) use ($dauthang, $hientai) {
                        $query->whereHas('donHang', function ($q) use ($dauthang, $hientai) {
                            $q->where('TrangThai', DonHang::TRANG_THAI_HOAN_TAT)
                                ->whereBetween('date', [$dauthang, $hientai]);
                        });
                    }
                ], 'SoLuong')
                ->orderByDesc('tong_ban')
                ->take(50)
                ->get();

            // Get recent activities from Database
            $activities = [];

            // Shop registrations
            $recentShops = \App\Models\Shop::orderBy('NgayDangKy', 'DESC')->take(15)->get();
            foreach ($recentShops as $shop) {
                $activities[] = [
                    'id' => 'shop_' . $shop->ID_Shop,
                    'tieude' => "Gian hàng mới đăng ký: " . $shop->TenShop,
                    'thoigian' => $shop->NgayDangKy ? $shop->NgayDangKy->toIso8601String() : null,
                    'timestamp' => $shop->NgayDangKy ? $shop->NgayDangKy->timestamp : 0,
                    'trangthai' => $shop->TrangThaiDuyet === 'cho_duyet' ? 'Chờ duyệt' : ($shop->TrangThaiDuyet === 'da_duyet' ? 'Đã duyệt' : 'Từ chối'),
                    'type' => 'shop',
                ];
            }

            // User registrations
            $recentUsers = \App\Models\User::where('ID_role', '!=', 1)->orderBy('ngaydangki', 'DESC')->take(15)->get();
            foreach ($recentUsers as $usr) {
                $roleName = $usr->ID_role == 3 ? 'Người bán' : 'Người mua';
                $activities[] = [
                    'id' => 'user_' . $usr->ID_User,
                    'tieude' => "Thành viên mới đăng ký (" . $roleName . "): " . ($usr->HoTen ?: $usr->email),
                    'thoigian' => $usr->ngaydangki ? $usr->ngaydangki->toIso8601String() : null,
                    'timestamp' => $usr->ngaydangki ? $usr->ngaydangki->timestamp : 0,
                    'trangthai' => 'Mới',
                    'type' => 'user',
                ];
            }

            // Order activities
            $recentOrders = \App\Models\DonHang::with('nguoiMua')->orderBy('date', 'DESC')->take(15)->get();
            foreach ($recentOrders as $order) {
                $buyerName = $order->nguoiMua ? ($order->nguoiMua->HoTen ?: $order->nguoiMua->email) : 'Khách';
                $statusText = 'Mới';
                if ($order->TrangThai === 1) $statusText = 'Đã xác nhận';
                if ($order->TrangThai === 2) $statusText = 'Đang giao';
                if ($order->TrangThai === 3) $statusText = 'Hoàn tất';
                if ($order->TrangThai === 4) $statusText = 'Đã hủy';

                $activities[] = [
                    'id' => 'order_' . $order->ID_DonHang,
                    'tieude' => "Đơn hàng mới từ " . $buyerName . " (Mã: " . $order->MaDonHangCon . ")",
                    'thoigian' => $order->date ? $order->date->toIso8601String() : null,
                    'timestamp' => $order->date ? $order->date->timestamp : 0,
                    'trangthai' => $statusText,
                    'type' => 'order',
                ];
            }

            // Sort by timestamp DESC
            usort($activities, function($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });

            // Limit to 30 items
            $activities = array_slice($activities, 0, 30);

            return response()->json([
                'success' => true,
                'message' => 'Tải dữ liệu quản trị thành công.',
                'data'    => [
                    'user'      => [
                        'ID_User'  => $user->ID_User,
                        'HoTen'    => $user->HoTen,
                        'email'    => $user->email,
                        'Ten_role' => $user->role?->Ten_role,
                    ],
                    'dashboard' => 'Admin Dashboard',
                    'stats'     => [
                        'doanh_thu_hom_nay'        => $tongDThomNay,
                        'doanh_thu_hom_nay_growth' => $ptDTHomNay,

                        'user_hom_nay'             => $userHomNay,
                        'user_hom_nay_growth'      => $ptUserHomNay,

                        'don_hang_hom_nay'         => $donHangHomNay,
                        'don_hang_hom_nay_growth'  => $ptDonHangHomNay,

                        'gian_hang_hom_nay'        => $gianHangHomNay,
                        'gian_hang_hom_nay_growth' => $ptGianHangHomNay,

                        'shop_cho_duyet'           => $shopChoDuyetTong,
                    ],
                    'bieu_do_doanh_thu' => $duLieuBieuDo,
                    'TopSP'=>$topProducts,
                    'activities' => $activities
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống Dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard dành cho Người Bán.
     *
     * GET /api/seller/dashboard
     * Middleware: auth:sanctum, role:NguoiBan
     */
    public function sellerDashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load('role');
            $shop = \App\Models\Shop::where('ID_User', $user->ID_User)->first();

            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa đăng ký gian hàng.',
                    'data'    => null
                ], 404);
            }

            $dauHomNay = \Carbon\Carbon::now()->startOfDay();
            $ngayHomNay = \Carbon\Carbon::now();
            $dauThang = \Carbon\Carbon::now()->startOfMonth();

            // --- 1. KPI STATS ---
            $tongSanPham = \App\Models\Product::where('ID_Shop', $shop->ID_Shop)->count();
            $choXuLy = \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 0)->count();
            $doanhThuHomNay = (float) \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)->whereBetween('date', [$dauHomNay, $ngayHomNay])->sum('TongGia');
            $doanhThuThang = (float) \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)->whereBetween('date', [$dauThang, $ngayHomNay])->sum('TongGia');
            $tongDonHang = \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)->count();

            // Average rating
            $ratingAvg = DB::table('danhgia')
                ->join('sanpham', 'danhgia.ID_SanPham', '=', 'sanpham.ID_SanPham')
                ->where('sanpham.ID_Shop', $shop->ID_Shop)
                ->avg('danhgia.XepLoai');
            $rating = round($ratingAvg ?: 5.0, 1);

            // --- 2. REVENUE GROWTH CHART ---
            $loaiBieuDo = $request->input('loai_bieu_do', '7_ngay');
            $duLieuBieuDo = [];

            if ($loaiBieuDo === '6_thang') {
                $tuNgay = \Carbon\Carbon::now()->subMonths(5)->startOfMonth();
                $denNgay = \Carbon\Carbon::now()->endOfMonth();

                $rawChartData = \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)
                    ->where('TrangThai', 3)
                    ->whereBetween('date', [$tuNgay, $denNgay])
                    ->selectRaw("DATE_FORMAT(date, '%m-%Y') as thoi_gian")
                    ->selectRaw("SUM(TongGia) as doanh_thu")
                    ->selectRaw("COUNT(ID_DonHang) as so_don")
                    ->groupBy(DB::raw("DATE_FORMAT(date, '%m-%Y')"))
                    ->orderBy('date', 'ASC')
                    ->get()
                    ->keyBy('thoi_gian');

                $thangChay = $tuNgay->copy();
                while ($thangChay->lte($denNgay)) {
                    $key = $thangChay->format('m-Y');
                    $duLieuBieuDo[] = [
                        'nhan' => "Tháng " . $thangChay->format('m/Y'),
                        'doanh_thu' => isset($rawChartData[$key]) ? (float)$rawChartData[$key]->doanh_thu : 0.0,
                        'so_don' => isset($rawChartData[$key]) ? (int)$rawChartData[$key]->so_don : 0
                    ];
                    $thangChay->addMonth();
                }
            } else {
                $tuNgay = \Carbon\Carbon::now()->subDays(6)->startOfDay();
                $denNgay = \Carbon\Carbon::now()->endOfDay();

                $rawChartData = \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)
                    ->where('TrangThai', 3)
                    ->whereBetween('date', [$tuNgay, $denNgay])
                    ->selectRaw("DATE(date) as thoi_gian")
                    ->selectRaw("SUM(TongGia) as doanh_thu")
                    ->selectRaw("COUNT(ID_DonHang) as so_don")
                    ->groupBy(DB::raw("DATE(date)"))
                    ->orderBy('thoi_gian', 'ASC')
                    ->get()
                    ->keyBy('thoi_gian');

                $ngayChay = $tuNgay->copy();
                while ($ngayChay->lte($denNgay)) {
                    $key = $ngayChay->format('Y-m-d');
                    $duLieuBieuDo[] = [
                        'nhan' => $ngayChay->format('d/m'),
                        'doanh_thu' => isset($rawChartData[$key]) ? (float)$rawChartData[$key]->doanh_thu : 0.0,
                        'so_don' => isset($rawChartData[$key]) ? (int)$rawChartData[$key]->so_don : 0
                    ];
                    $ngayChay->addDay();
                }
            }

            // --- 3. TOP SELLING PRODUCTS ---
            $topProducts = \App\Models\Product::where('ID_Shop', $shop->ID_Shop)
                ->withSum([
                    'chiTietDonHang as tong_ban' => function ($query) use ($dauThang, $ngayHomNay) {
                        $query->whereHas('donHang', function ($q) use ($dauThang, $ngayHomNay) {
                            $q->where('TrangThai', \App\Models\DonHang::TRANG_THAI_HOAN_TAT)
                              ->whereBetween('date', [$dauThang, $ngayHomNay]);
                        });
                    }
                ], 'SoLuong')
                ->orderByDesc('tong_ban')
                ->take(10)
                ->get();

            // --- 4. RECENT ORDERS ---
            $recentOrders = \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)
                ->with('nguoiMua')
                ->orderBy('date', 'DESC')
                ->take(10)
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->MaDonHangCon ?: ('DH-' . $order->ID_DonHang),
                        'customer' => $order->nguoiMua ? ($order->nguoiMua->HoTen ?: $order->nguoiMua->email) : 'Khách',
                        'product' => $order->chiTiet()->with('sanPham')->get()->map(function($ct) {
                            return ($ct->sanPham?->TenSanPham ?: 'Sản phẩm') . ' (x' . $ct->SoLuong . ')';
                        })->implode(', '),
                        'total' => (float)$order->TongGia,
                        'status' => $order->TrangThai === 0 ? 'Chờ xác nhận' : ($order->TrangThai === 1 ? 'Đã xác nhận' : ($order->TrangThai === 2 ? 'Đang giao' : ($order->TrangThai === 3 ? 'Hoàn thành' : 'Đã hủy')))
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Tải dữ liệu tổng quan người bán thành công.',
                'data'    => [
                    'shop' => [
                        'ID_Shop' => $shop->ID_Shop,
                        'TenShop' => $shop->TenShop,
                        'LoaiHinhKinhDoanh' => $shop->LoaiHinhKinhDoanh,
                        'TrangThaiDuyet' => $shop->TrangThaiDuyet,
                        'LyDoTuChoi' => $shop->LyDoTuChoi,
                    ],
                    'stats' => [
                        'tong_san_pham' => $tongSanPham,
                        'cho_xu_ly' => $choXuLy,
                        'doanh_thu_hom_nay' => $doanhThuHomNay,
                        'doanh_thu_thang' => $doanhThuThang,
                        'tong_don_hang' => $tongDonHang,
                        'rating' => $rating,
                    ],
                    'bieu_do_doanh_thu' => $duLieuBieuDo,
                    'TopSP' => $topProducts,
                    'recent_orders' => $recentOrders
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống Seller Dashboard: ' . $e->getMessage()
            ], 500);
        }
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

    /**
     * Lấy danh sách các hoạt động/thông báo gần đây của Seller dynamically từ DB
     * 
     * GET /api/seller/activities
     */
    public function sellerActivities(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $shop = \App\Models\Shop::where('ID_User', $user->ID_User)->first();
            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có gian hàng.'
                ], 403);
            }

            $activities = [];

            // 1. Đơn hàng mới / Cập nhật trạng thái đơn
            $recentOrders = \App\Models\DonHang::where('ID_Shop', $shop->ID_Shop)->with('nguoiMua')->orderBy('date', 'DESC')->take(15)->get();
            foreach ($recentOrders as $order) {
                $buyerName = $order->nguoiMua ? ($order->nguoiMua->HoTen ?: $order->nguoiMua->email) : 'Khách';
                $statusText = 'Mới';
                if ($order->TrangThai === 1) $statusText = 'Đã xác nhận';
                if ($order->TrangThai === 2) $statusText = 'Đang giao';
                if ($order->TrangThai === 3) $statusText = 'Hoàn tất';
                if ($order->TrangThai === 4) $statusText = 'Đã hủy';

                $activities[] = [
                    'id' => 'order_' . $order->ID_DonHang . '_' . $order->TrangThai,
                    'tieude' => "Đơn hàng của " . $buyerName . " ở trạng thái " . $statusText . " (Mã: " . $order->MaDonHangCon . ")",
                    'thoigian' => $order->date ? $order->date->toIso8601String() : null,
                    'timestamp' => $order->date ? $order->date->timestamp : 0,
                    'trangthai' => $statusText,
                    'type' => 'order',
                ];
            }

            // 2. Đánh giá mới
            $recentReviews = DB::table('danhgia')
                ->join('sanpham', 'danhgia.ID_SanPham', '=', 'sanpham.ID_SanPham')
                ->where('sanpham.ID_Shop', $shop->ID_Shop)
                ->select('danhgia.*', 'sanpham.TenSanPham')
                ->orderBy('danhgia.ThoiGian', 'DESC')
                ->take(15)
                ->get();

            foreach ($recentReviews as $rev) {
                $activities[] = [
                    'id' => 'review_' . $rev->ID_DanhGia,
                    'tieude' => "Đánh giá " . $rev->XepLoai . " sao cho sản phẩm: " . $rev->TenSanPham,
                    'thoigian' => $rev->ThoiGian ? \Carbon\Carbon::parse($rev->ThoiGian)->toIso8601String() : null,
                    'timestamp' => $rev->ThoiGian ? \Carbon\Carbon::parse($rev->ThoiGian)->timestamp : 0,
                    'trangthai' => 'Mới',
                    'type' => 'review',
                ];
            }

            // 3. Sản phẩm bị ẩn/duyệt
            $recentProducts = \App\Models\Product::where('ID_Shop', $shop->ID_Shop)
                ->whereNotNull('TrangThaiDuyet')
                ->orderBy('updated_at', 'DESC')
                ->take(15)
                ->get();

            foreach ($recentProducts as $prod) {
                $statusText = $prod->TrangThaiDuyet === 'da_duyet' ? 'Đã duyệt' : ($prod->TrangThaiDuyet === 'tu_choi' ? 'Từ chối' : 'Chờ duyệt');
                $activities[] = [
                    'id' => 'product_' . $prod->ID_SanPham . '_' . $prod->TrangThaiDuyet,
                    'tieude' => "Sản phẩm \"" . $prod->TenSanPham . "\" có trạng thái: " . $statusText,
                    'thoigian' => $prod->updated_at ? $prod->updated_at->toIso8601String() : null,
                    'timestamp' => $prod->updated_at ? $prod->updated_at->timestamp : 0,
                    'trangthai' => $statusText,
                    'type' => 'product',
                ];
            }

            // Sắp xếp giảm dần theo thời gian
            usort($activities, function ($a, $b) {
                return $b['timestamp'] <=> $a['timestamp'];
            });

            // Giới hạn 20 thông báo gần nhất
            $activities = array_slice($activities, 0, 20);

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi lấy thông báo hoạt động: ' . $e->getMessage()
            ], 500);
        }
    }
}
