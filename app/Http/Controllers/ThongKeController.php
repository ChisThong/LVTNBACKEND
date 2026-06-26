<?php

namespace App\Http\Controllers;

use App\Models\DonHang;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use App\Models\TinhThanh;
use App\Models\PhanLoaiSP;
use Illuminate\Support\Facades\DB;

class ThongKeController extends Controller
{
    public function AdminThongKeDanhThu(Request $request)
    {
        try {
            $TuNgay = $request->input('tungay')
                ? Carbon::parse($request->input('tungay'))->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay();
            $DenNgay = $request->input('denngay')
                ? Carbon::parse($request->input('denngay'))->endOfDay()
                : Carbon::now()->endOfDay();

            $type = $request->input('Loai', 'date');
            $bieudoquery = DonHang::where('TrangThai', 3)->betweenDate($TuNgay, $DenNgay);
            switch ($type) {
                case 'month':
                    $bieudoquery->selectRaw("DATE_FORMAT(date,'%m-%Y') as tg")
                        ->groupBy(DB::raw("DATE_FORMAT(date,'%m-%Y')"));
                    break;
                case 'quarter':
                    $bieudoquery->selectRaw("CONCAT(YEAR(date), ' - Q', QUARTER(date)) as tg")
                        ->groupBy(DB::raw("CONCAT(YEAR(date), ' - Q', QUARTER(date))"));
                    break;
                case 'year':
                    $bieudoquery->selectRaw("YEAR(date) as tg")
                        ->groupBy(DB::raw("YEAR(date)"));
                    break;
                case 'date':
                default:
                    $bieudoquery->selectRaw("DATE(date) as tg")
                        ->groupBy(DB::raw("DATE(date)"));
                    break;
            }
            $bieudoData = $bieudoquery->selectRaw('SUM(TongGia) as doanh_thu, COUNT(ID_DonHang) as so_don')
                ->orderBy('tg', 'ASC')
                ->get();

            // Điền giá trị 0 cho những ngày/khoảng thời gian không có doanh số
            $mappedData = [];
            foreach ($bieudoData as $row) {
                $mappedData[$row->tg] = [
                    'tg' => $row->tg,
                    'doanh_thu' => (float)$row->doanh_thu,
                    'so_don' => (int)$row->so_don,
                ];
            }

            $filledData = [];
            $current = $TuNgay->copy();

            if ($type === 'month') {
                $end = $DenNgay->copy()->startOfMonth();
                $current->startOfMonth();
                while ($current->lte($end)) {
                    $key = $current->format('m-Y');
                    $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                        'tg' => $key,
                        'doanh_thu' => 0.0,
                        'so_don' => 0
                    ];
                    $current->addMonth();
                }
            } elseif ($type === 'quarter') {
                $tempQuarters = [];
                while ($current->lte($DenNgay)) {
                    $q = (int)ceil($current->month / 3);
                    $key = $current->year . ' - Q' . $q;
                    if (!isset($tempQuarters[$key])) {
                        $tempQuarters[$key] = true;
                        $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                            'tg' => $key,
                            'doanh_thu' => 0.0,
                            'so_don' => 0
                        ];
                    }
                    $current->addMonth()->startOfMonth();
                }
            } elseif ($type === 'year') {
                $end = $DenNgay->copy()->startOfYear();
                $current->startOfYear();
                while ($current->lte($end)) {
                    $key = (string)$current->year;
                    $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                        'tg' => $key,
                        'doanh_thu' => 0.0,
                        'so_don' => 0
                    ];
                    $current->addYear();
                }
            } else {
                $end = $DenNgay->copy()->startOfDay();
                $current->startOfDay();
                while ($current->lte($end)) {
                    $key = $current->format('Y-m-d');
                    $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                        'tg' => $key,
                        'doanh_thu' => 0.0,
                        'so_don' => 0
                    ];
                    $current->addDay();
                }
            }

            // Gán dữ liệu biểu đồ đã điền đầy đủ
            $bieudoDT_Data = $filledData;

            $dauThangNay = Carbon::now()->startOfMonth();
            $hienTai = Carbon::now();
            $dauThangTruoc = Carbon::now()->subMonth()->startOfMonth();
            $hetThangTruoc = Carbon::now()->subMonth()->endOfMonth();
            $tinhPhanTram = function ($thangNay, $thangTruoc) {
                if ($thangTruoc == 0) {
                    return $thangNay > 0 ? 100.0 : 0.0;
                }
                return round((($thangNay - $thangTruoc) / $thangTruoc) * 100, 2);
            };
            $tongDTthangnay = (float) DonHang::where('TrangThai', 3)
                ->betweenDate($dauThangNay, $hienTai)
                ->sum('TongGia');
            $tongDTthangtruoc = (float) DonHang::where('TrangThai', 3)
                ->betweenDate($dauThangTruoc, $hetThangTruoc)
                ->sum('TongGia');
            $tongDHthangnay = DonHang::where('TrangThai', 3)
                ->betweenDate($dauThangNay, $hienTai)
                ->count();
            $tongDHthangtruoc = DonHang::where('TrangThai', 3)
                ->betweenDate($dauThangTruoc, $hetThangTruoc)
                ->count();
            $gianhangnow = Shop::betweenDate($dauThangNay, $hienTai)->count();
            $gianhangtruoc = Shop::betweenDate($dauThangTruoc, $hetThangTruoc)->count();
            $spnow = Product::betweenDate($dauThangNay, $hienTai)->count();
            $sptruoc = Product::betweenDate($dauThangTruoc, $hetThangTruoc)->count();
            $usernow = User::where('ID_role', 2)->betweenDate($dauThangNay, $hienTai)->count();
            $usertruoc = User::where('ID_role', 2)->betweenDate($dauThangTruoc, $hetThangTruoc)->count();

            $topProducts = Product::query()
                ->withSum([
                    'chiTietDonHang as tong_ban' => function ($query) use ($TuNgay, $DenNgay) {
                        $query->whereHas('donHang', function ($q) use ($TuNgay, $DenNgay) {
                            $q->where('TrangThai', DonHang::TRANG_THAI_HOAN_TAT)
                                ->whereBetween('date', [$TuNgay, $DenNgay]);
                        });
                    }
                ], 'SoLuong')
                ->orderByDesc('tong_ban')
                ->take(50)
                ->get();

            $topshops = Shop::query()
                ->withSum([
                    'chiTietDonHang as doanh_thu' => function ($query) use ($TuNgay, $DenNgay) {
                        $query->whereHas('donHang', function ($q) use ($TuNgay, $DenNgay) {
                            $q->where('TrangThai', DonHang::TRANG_THAI_HOAN_TAT)
                                ->whereBetween('date', [$TuNgay, $DenNgay]);
                        });
                    }
                ], 'TongGia')
                ->orderByDesc('doanh_thu')
                ->take(50)
                ->get();
            $thongKeTinhThanh = TinhThanh::query()
                ->withCount([
                    'products' => function ($query) {
                        $query->where('TrangThai', 1)
                            ->where('TrangThaiDuyet', 'da_duyet');
                    }
                ])
                ->orderByDesc('products_count')
                ->take(10)
                ->get();
            $mappedTinhThanhData = $thongKeTinhThanh->map(function ($tinh) {
                return [
                    'tinh_thanh' => $tinh->TenTinhThanh,
                    'so_luong' => (int)$tinh->products_count 
                ];
            });
            $thongKeDanhMuc = PhanLoaiSp::query()
                ->withCount('products') 
                ->orderByDesc('products_count')
                ->take(10)
                ->get()
                ->map(function ($cat) {
                    return [
                        'ten_loai' => $cat->TenLoai,
                        'so_luong' => (int)$cat->products_count
                    ];
                });

            $thongKeBlogTinhThanh = TinhThanh::query()
                ->withCount('blogs')
                ->orderByDesc('blogs_count')
                ->get()
                ->map(function ($tinh) {
                    return [
                        'tinh_thanh' => $tinh->TenTinhThanh,
                        'so_luong_blog' => (int)$tinh->blogs_count
                    ];
                });
            $choXuLy = [
                'shop_cho_duyet' => (int) Shop::where('TrangThaiDuyet', 'cho_duyet')->count(),
                'sp_cho_duyet' => (int) Product::where('TrangThaiDuyet', 'cho_duyet')->count()
            ];

            return response()->json([
                'success' => true,
                'TongDTthangnay' => $tongDTthangnay,
                'phantramDT' => $tinhPhanTram($tongDTthangnay, $tongDTthangtruoc),
                'bieudoDT' => $bieudoDT_Data,
                'DHthangnay' => $tongDHthangnay,
                'phantramDH' => $tinhPhanTram($tongDHthangnay, $tongDHthangtruoc),
                'GHthangnay' => $gianhangnow,
                'phantramGH' => $tinhPhanTram($gianhangnow, $gianhangtruoc),
                'SLsp' => $spnow,
                'phantramSP' => $tinhPhanTram($spnow, $sptruoc),
                'Sluser' => $usernow,
                'phamtramuser' => $tinhPhanTram($usernow, $usertruoc),
                'Topsp' => $topProducts,
                'Topshop' => $topshops,
                'TinhThanhTK' => $mappedTinhThanhData,
                'DanhMucTK' => $thongKeDanhMuc,
                'BlogTinhThanhTK' => $thongKeBlogTinhThanh,
                'AdminChoXuLy' => $choXuLy,

            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Lỗi hệ thống: " . $e->getMessage()
            ], 500);
        }
    }

    public function SellerThongKeDanhThu(Request $request)
    {
        try {
            $user = $request->user();
            $shop = Shop::where('ID_User', $user->ID_User)->first();
            if (!$shop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn chưa đăng ký gian hàng.'
                ], 404);
            }

            $TuNgay = $request->input('tungay')
                ? Carbon::parse($request->input('tungay'))->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay();
            $DenNgay = $request->input('denngay')
                ? Carbon::parse($request->input('denngay'))->endOfDay()
                : Carbon::now()->endOfDay();

            $type = $request->input('Loai', 'date');
            $bieudoquery = DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)->betweenDate($TuNgay, $DenNgay);
            switch ($type) {
                case 'month':
                    $bieudoquery->selectRaw("DATE_FORMAT(date,'%m-%Y') as tg")
                        ->groupBy(DB::raw("DATE_FORMAT(date,'%m-%Y')"));
                    break;
                case 'quarter':
                    $bieudoquery->selectRaw("CONCAT(YEAR(date), ' - Q', QUARTER(date)) as tg")
                        ->groupBy(DB::raw("CONCAT(YEAR(date), ' - Q', QUARTER(date))"));
                    break;
                case 'year':
                    $bieudoquery->selectRaw("YEAR(date) as tg")
                        ->groupBy(DB::raw("YEAR(date)"));
                    break;
                case 'date':
                default:
                    $bieudoquery->selectRaw("DATE(date) as tg")
                        ->groupBy(DB::raw("DATE(date)"));
                    break;
            }
            $bieudoData = $bieudoquery->selectRaw('SUM(TongGia) as doanh_thu, COUNT(ID_DonHang) as so_don')
                ->orderBy('tg', 'ASC')
                ->get();

            $mappedData = [];
            foreach ($bieudoData as $row) {
                $mappedData[$row->tg] = [
                    'tg' => $row->tg,
                    'doanh_thu' => (float)$row->doanh_thu,
                    'so_don' => (int)$row->so_don,
                ];
            }

            $filledData = [];
            $current = $TuNgay->copy();

            if ($type === 'month') {
                $end = $DenNgay->copy()->startOfMonth();
                $current->startOfMonth();
                while ($current->lte($end)) {
                    $key = $current->format('m-Y');
                    $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                        'tg' => $key,
                        'doanh_thu' => 0.0,
                        'so_don' => 0
                    ];
                    $current->addMonth();
                }
            } elseif ($type === 'quarter') {
                $tempQuarters = [];
                while ($current->lte($DenNgay)) {
                    $q = (int)ceil($current->month / 3);
                    $key = $current->year . ' - Q' . $q;
                    if (!isset($tempQuarters[$key])) {
                        $tempQuarters[$key] = true;
                        $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                            'tg' => $key,
                            'doanh_thu' => 0.0,
                            'so_don' => 0
                        ];
                    }
                    $current->addMonth()->startOfMonth();
                }
            } elseif ($type === 'year') {
                $end = $DenNgay->copy()->startOfYear();
                $current->startOfYear();
                while ($current->lte($end)) {
                    $key = (string)$current->year;
                    $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                        'tg' => $key,
                        'doanh_thu' => 0.0,
                        'so_don' => 0
                    ];
                    $current->addYear();
                }
            } else {
                $end = $DenNgay->copy()->startOfDay();
                $current->startOfDay();
                while ($current->lte($end)) {
                    $key = $current->format('Y-m-d');
                    $filledData[] = isset($mappedData[$key]) ? $mappedData[$key] : [
                        'tg' => $key,
                        'doanh_thu' => 0.0,
                        'so_don' => 0
                    ];
                    $current->addDay();
                }
            }

            $bieudoDT_Data = $filledData;

            $dauThangNay = Carbon::now()->startOfMonth();
            $hienTai = Carbon::now();
            $dauThangTruoc = Carbon::now()->subMonth()->startOfMonth();
            $hetThangTruoc = Carbon::now()->subMonth()->endOfMonth();
            $tinhPhanTram = function ($thangNay, $thangTruoc) {
                if ($thangTruoc == 0) {
                    return $thangNay > 0 ? 100.0 : 0.0;
                }
                return round((($thangNay - $thangTruoc) / $thangTruoc) * 100, 2);
            };

            $tongDTthangnay = (float) DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)
                ->betweenDate($dauThangNay, $hienTai)
                ->sum('TongGia');
            $tongDTthangtruoc = (float) DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)
                ->betweenDate($dauThangTruoc, $hetThangTruoc)
                ->sum('TongGia');

            $tongDHthangnay = DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)
                ->betweenDate($dauThangNay, $hienTai)
                ->count();
            $tongDHthangtruoc = DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 3)
                ->betweenDate($dauThangTruoc, $hetThangTruoc)
                ->count();

            $spnow = Product::where('ID_Shop', $shop->ID_Shop)->betweenDate($dauThangNay, $hienTai)->count();
            $sptruoc = Product::where('ID_Shop', $shop->ID_Shop)->betweenDate($dauThangTruoc, $hetThangTruoc)->count();

            // Avg rating
            $ratingAvg = (float) DB::table('danhgia')
                ->join('sanpham', 'danhgia.ID_SanPham', '=', 'sanpham.ID_SanPham')
                ->where('sanpham.ID_Shop', $shop->ID_Shop)
                ->avg('danhgia.XepLoai');
            $ratingVal = round($ratingAvg ?: 5.0, 1);

            $ratingCountNow = DB::table('danhgia')
                ->join('sanpham', 'danhgia.ID_SanPham', '=', 'sanpham.ID_SanPham')
                ->where('sanpham.ID_Shop', $shop->ID_Shop)
                ->whereBetween('danhgia.NgayDanhGia', [$dauThangNay, $hienTai])
                ->count();
            $ratingCountTruoc = DB::table('danhgia')
                ->join('sanpham', 'danhgia.ID_SanPham', '=', 'sanpham.ID_SanPham')
                ->where('sanpham.ID_Shop', $shop->ID_Shop)
                ->whereBetween('danhgia.NgayDanhGia', [$dauThangTruoc, $hetThangTruoc])
                ->count();

            $topProducts = Product::where('ID_Shop', $shop->ID_Shop)
                ->withSum([
                    'chiTietDonHang as tong_ban' => function ($query) use ($TuNgay, $DenNgay) {
                        $query->whereHas('donHang', function ($q) use ($TuNgay, $DenNgay) {
                            $q->where('TrangThai', DonHang::TRANG_THAI_HOAN_TAT)
                                ->whereBetween('date', [$TuNgay, $DenNgay]);
                        });
                    }
                ], 'SoLuong')
                ->orderByDesc('tong_ban')
                ->take(50)
                ->get();

            // Order status count breakdown
            $statusCounts = DonHang::where('ID_Shop', $shop->ID_Shop)
                ->betweenDate($TuNgay, $DenNgay)
                ->select('TrangThai', DB::raw('count(*) as status_count'))
                ->groupBy('TrangThai')
                ->get()
                ->map(function ($row) {
                    $labels = [
                        0 => 'Chờ xác nhận',
                        1 => 'Đã xác nhận',
                        2 => 'Đang giao',
                        3 => 'Hoàn thành',
                        4 => 'Đã hủy'
                    ];
                    return [
                        'status' => $row->TrangThai,
                        'name' => isset($labels[$row->TrangThai]) ? $labels[$row->TrangThai] : 'Khác',
                        'value' => (int)$row->status_count
                    ];
                });

            $choXuLy = [
                'sp_cho_duyet' => (int) Product::where('ID_Shop', $shop->ID_Shop)->where('TrangThaiDuyet', 'cho_duyet')->count(),
                'don_hang_cho_xac_nhan' => (int) DonHang::where('ID_Shop', $shop->ID_Shop)->where('TrangThai', 0)->count()
            ];

            return response()->json([
                'success' => true,
                'TongDTthangnay' => $tongDTthangnay,
                'phantramDT' => $tinhPhanTram($tongDTthangnay, $tongDTthangtruoc),
                'bieudoDT' => $bieudoDT_Data,
                'DHthangnay' => $tongDHthangnay,
                'phantramDH' => $tinhPhanTram($tongDHthangnay, $tongDHthangtruoc),
                'SLsp' => $spnow,
                'phantramSP' => $tinhPhanTram($spnow, $sptruoc),
                'Topsp' => $topProducts,
                'RatingAvg' => $ratingVal,
                'RatingCount' => $ratingCountNow,
                'phantramRating' => $tinhPhanTram($ratingCountNow, $ratingCountTruoc),
                'OrderStatusBreakdown' => $statusCounts,
                'SellerChoXuLy' => $choXuLy,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Lỗi hệ thống: " . $e->getMessage()
            ], 500);
        }
    }
}
