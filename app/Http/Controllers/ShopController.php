<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\RegisterShopRequest;
use App\Http\Requests\Shop\UpdateShopRequest;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShopController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // POST /api/seller/shop/register
    // Middleware: auth:sanctum (bất kỳ role đã đăng nhập đều được đăng ký)
    // Mỗi user chỉ được có 1 shop.
    // ─────────────────────────────────────────────────────────────────────────
    public function register(RegisterShopRequest $request): JsonResponse
    {
        $user = $request->user();

        // Kiểm tra đã có shop chưa
        if ($user->shop()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã đăng ký gian hàng rồi. Mỗi tài khoản chỉ được có 1 gian hàng.',
                'data'    => $user->shop,
            ], 422);
        }

        $shop = Shop::create([
            'TenShop'        => $request->TenShop,
            'SCCD'           => $request->SCCD,
            'SoDienThoai'    => $request->SoDienThoai,
            'DiaChi'         => $request->DiaChi,
            'SoTaiKhoang'    => $request->SoTaiKhoang,
            'TenNganHang'    => $request->TenNganHang,
            'Tittle'         => $request->Tittle,
            'GioiThieu'      => $request->GioiThieu,
            'NgayDangKy'     => now(),
            'NgayDuyet'      => null,
            'TrangThaiDuyet' => Shop::DUYET_CHO,
            'TrangThai'      => 1,
            'LoaiHinhKinhDoanh' => $request->LoaiHinhKinhDoanh,
            'ID_User'        => $user->ID_User,
        ]);

        $activityData = [
            'id_target' => $shop->ID_Shop,
            'tieude' => "Cửa hàng " . $shop->TenShop . " mới gửi yêu cầu duyệt",
            'thoigian' => now()->toDateTimeString(),
            'trangthai' => 'Chờ duyệt',
            'type' => 'shop'
        ];

        // Bắn tín hiệu sang Pusher
        event(new \App\Events\AdminActivityEvent($activityData));

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký gian hàng thành công. Vui lòng chờ Admin xét duyệt.',
            'data'    => $shop->load('user:ID_User,HoTen,email'),
            'activities' => $activityData

        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/seller/shop
    // Middleware: auth:sanctum
    // Xem shop của mình.
    // ─────────────────────────────────────────────────────────────────────────
    public function myShop(Request $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng ký gian hàng.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $shop->load('user:ID_User,HoTen,email'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/seller/shop
    // Middleware: auth:sanctum
    // Cập nhật thông tin shop của mình.
    // ─────────────────────────────────────────────────────────────────────────
    public function update(UpdateShopRequest $request): JsonResponse
    {
        $shop = $request->user()->shop;

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa có gian hàng để cập nhật.',
            ], 404);
        }

        $data = $request->safe()->except(['logo', 'baner']);

        // Upload logo nếu có
        if ($request->hasFile('logo')) {
            if ($shop->logo) {
                Storage::disk('public')->delete($shop->logo);
            }
            $data['logo'] = $request->file('logo')->store('shops/logo', 'public');
        }

        // Upload banner nếu có
        if ($request->hasFile('baner')) {
            if ($shop->baner) {
                Storage::disk('public')->delete($shop->baner);
            }
            $data['baner'] = $request->file('baner')->store('shops/baner', 'public');
        }

        // Nếu shop đang bị từ chối, cập nhật lại sẽ reset về chờ duyệt
        if ($shop->TrangThaiDuyet === Shop::DUYET_TU_CHOI) {
            $data['TrangThaiDuyet'] = Shop::DUYET_CHO;
            $data['LyDoTuChoi'] = null;
        }

        $shop->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật gian hàng thành công.',
            'data'    => $shop->fresh()->load('user:ID_User,HoTen,email'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/shops
    // Middleware: auth:sanctum + role:Admin
    // Danh sách tất cả shop (có filter theo TrangThaiDuyet).
    // ─────────────────────────────────────────────────────────────────────────
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Shop::with('user:ID_User,HoTen,email,sdt')->withCount('products');

        // Lọc theo trạng thái duyệt
        if ($request->filled('trang_thai_duyet')) {
            $query->where('TrangThaiDuyet', $request->trang_thai_duyet);
        }

        // Tìm kiếm
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('TenShop', 'like', "%{$search}%")
                    ->orWhere('SoDienThoai', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($qUser) use ($search) {
                        $qUser->where('HoTen', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('sdt', 'like', "%{$search}%");
                    });
            });
        }

        $shops = $query->orderByDesc('NgayDangKy')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $shops,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/admin/shops/{id}/approve
    // Middleware: auth:sanctum + role:Admin
    // ─────────────────────────────────────────────────────────────────────────
    public function approve(int $id): JsonResponse
    {
        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng không tồn tại.',
            ], 404);
        }

        if ($shop->TrangThaiDuyet === Shop::DUYET_DA) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng này đã được duyệt trước đó.',
                'data'    => $shop,
            ], 409);
        }

        $shop->update([
            'TrangThaiDuyet' => Shop::DUYET_DA,
            'NgayDuyet'      => now(),
            'LyDoTuChoi'     => null,
        ]);

        // ── Chuyển role user sang NguoiBan (ID_role = 3) ──────────────────────
        $shop->user()->update(['ID_role' => 3]);

        $activityData = [
            'id_target' => $shop->ID_Shop,
            'tieude' => "Gian hàng \"" . $shop->TenShop . "\" của bạn đã được Admin duyệt thành công!",
            'thoigian' => now()->toDateTimeString(),
            'trangthai' => 'Mới',
            'type' => 'shop'
        ];
        event(new \App\Events\SellerActivityEvent($activityData, $shop->ID_Shop));

        return response()->json([
            'success' => true,
            'message' => "Đã duyệt gian hàng \"{$shop->TenShop}\". Tài khoản đã được nâng cấp lên Người Bán.",
            'data'    => $shop->fresh()->load('user:ID_User,HoTen,email,ID_role'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/admin/shops/{id}/reject
    // Middleware: auth:sanctum + role:Admin
    // ─────────────────────────────────────────────────────────────────────────
    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'LyDoTuChoi' => 'nullable|string|max:1000',
        ]);

        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng không tồn tại.',
            ], 404);
        }

        if ($shop->TrangThaiDuyet === Shop::DUYET_TU_CHOI) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng này đã bị từ chối trước đó.',
                'data'    => $shop,
            ], 409);
        }

        $shop->update([
            'TrangThaiDuyet' => Shop::DUYET_TU_CHOI,
            'LyDoTuChoi'     => $request->input('LyDoTuChoi', $request->input('ly_do_tu_choi')),
        ]);

        // ── Nếu user đang là NguoiBan thì revert về NguoiMua (ID_role = 2) ───
        if ($shop->user && (int) $shop->user->ID_role === 3) {
            $shop->user()->update(['ID_role' => 2]);
        }

        $activityData = [
            'id_target' => $shop->ID_Shop,
            'tieude' => "Yêu cầu duyệt gian hàng \"" . $shop->TenShop . "\" đã bị từ chối. Lý do: " . $shop->LyDoTuChoi,
            'thoigian' => now()->toDateTimeString(),
            'trangthai' => 'Mới',
            'type' => 'shop'
        ];
        event(new \App\Events\SellerActivityEvent($activityData, $shop->ID_Shop));

        return response()->json([
            'success' => true,
            'message' => "Đã từ chối gian hàng \"{$shop->TenShop}\". Tài khoản đã trở về Người Mua.",
            'data'    => $shop->fresh()->load('user:ID_User,HoTen,email,ID_role'),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATCH /api/admin/shops/{id}/toggle-status
    // Middleware: auth:sanctum + role:Admin
    // ─────────────────────────────────────────────────────────────────────────
    public function toggleStatus($id): JsonResponse
    {
        $shop = Shop::where('ID_Shop', $id)->firstOrFail();

        $shop->TrangThai = $shop->TrangThai == 1 ? 0 : 1;

        $shop->save();

        return response()->json([
            'success' => true,
            'message' => $shop->TrangThai
                ? 'Đã mở lại gian hàng'
                : 'Đã khóa gian hàng',
            'TrangThai' => $shop->TrangThai
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/admin/shops/{id}/products
    // Middleware: auth:sanctum + role:Admin
    // Admin xem danh sách sản phẩm của một shop cụ thể.
    // ─────────────────────────────────────────────────────────────────────────
    public function shopProducts(Request $request, int $id): JsonResponse
    {
        $shop = Shop::find($id);

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng không tồn tại.',
            ], 404);
        }

        $perPage = (int) ($request->per_page ?? 20);

        $products = $shop->products()
            ->with(['hinhAnh', 'phanLoai', 'tinhThanh'])
            ->orderByDesc('ID_SanPham')
            ->paginate($perPage);

        return response()->json([
            'success'    => true,
            'message'    => "Danh sách sản phẩm của shop \"{$shop->TenShop}\".",
            'shop'       => [
                'ID_Shop'  => $shop->ID_Shop,
                'TenShop'  => $shop->TenShop,
            ],
            'data'       => $products,
        ]);
    }
    // ─────────────────────────────────────────────────────────────────────────
    // GET /api/shops/{id}
    // Public — Xem chi tiết 1 gian hàng + danh sách sản phẩm.
    // ─────────────────────────────────────────────────────────────────────────
    public function publicShow(int $id): JsonResponse
    {
        $shop = Shop::where('TrangThai', 1)
            ->where('TrangThaiDuyet', Shop::DUYET_DA)
            ->withCount(['products' => function ($q) {
                $q->where('TrangThai', 1)
                    ->where('TrangThaiDuyet', 'da_duyet')
                    ->where('TrangThaiHienThi', 'hien');
            }])
            ->find($id);

        if (! $shop) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng không tồn tại hoặc chưa được duyệt.',
            ], 404);
        }

        // Tải kèm danh sách sản phẩm đang bán và đang hiển
        $shop->load(['products' => function ($q) {
            $q->where('TrangThai', 1)
                ->where('TrangThaiDuyet', 'da_duyet')
                ->where('TrangThaiHienThi', 'hien')
                ->with(['hinhAnh', 'phanLoai', 'tinhThanh']);
        }]);

        return response()->json([
            'success' => true,
            'data'    => $shop,
        ]);
    }

    /**
     * Lấy số dư ví của Shop (Dựa trên User đang đăng nhập)
     * GET /api/seller/wallet
     */
    public function getWallet(Request $request): JsonResponse
    {
        $user = $request->user();

        // Lấy hoặc tạo ví cho người bán
        $wallet = \Illuminate\Support\Facades\DB::table('wallets')->where('user_id', $user->ID_User)->first();

        if (!$wallet) {
            $walletId = \Illuminate\Support\Facades\DB::table('wallets')->insertGetId([
                'user_id' => $user->ID_User,
                'balance' => 0,
                'frozen_balance' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $wallet = \Illuminate\Support\Facades\DB::table('wallets')->where('id', $walletId)->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $wallet->balance,
                'frozen_balance' => $wallet->frozen_balance
            ]
        ]);
    }

    /**
     * Seller cập nhật trạng thái đơn hàng (Đồng bộ doanh thu)
     * PUT /api/seller/orders/{id}/status
     */
    public function updateOrderStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'TrangThai' => 'required|integer|in:0,1,2,3,4'
        ]);

        $user = $request->user();
        $shop = Shop::where('ID_User', $user->ID_User)->first();

        if (!$shop) {
            return response()->json(['success' => false, 'message' => 'Bạn chưa có gian hàng.'], 403);
        }

        $newStatus = (int) $request->TrangThai;

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $donHang = \App\Models\DonHang::where('ID_DonHang', $id)
                ->where('ID_Shop', $shop->ID_Shop)
                ->lockForUpdate()
                ->first();

            if (!$donHang) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Không tìm thấy đơn hàng của bạn.'], 404);
            }

            // Nếu đơn đã hoàn tất thì không cập nhật lại tiền
            if ($donHang->TrangThai == \App\Models\DonHang::TRANG_THAI_HOAN_TAT && $newStatus == \App\Models\DonHang::TRANG_THAI_HOAN_TAT) {
                \Illuminate\Support\Facades\DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Đơn hàng đã được hoàn tất trước đó.'], 400);
            }

            $donHang->TrangThai = $newStatus;
            $donHang->save();

            // Cộng doanh thu khi đơn hàng HOÀN TẤT
            if ($newStatus == \App\Models\DonHang::TRANG_THAI_HOAN_TAT) {
                $tongThu = $donHang->TongGia + $donHang->PhiVanChuyen;
                $commission = $tongThu * 0.05; // 5% Admin
                $sellerAmount = $tongThu - $commission; // 95% Seller

                // 1. Cộng ví Seller
                $sellerWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $user->ID_User]);
                $sellerWallet = \App\Models\Wallet::lockForUpdate()->find($sellerWallet->id);

                $beforeSeller = $sellerWallet->balance;
                $sellerWallet->balance += $sellerAmount;
                $sellerWallet->save();

                \App\Models\WalletTransaction::create([
                    'wallet_id'      => $sellerWallet->id,
                    'type'           => 'revenue',
                    'status'         => 'completed',
                    'amount'         => $sellerAmount,
                    'balance_before' => $beforeSeller,
                    'balance_after'  => $sellerWallet->balance,
                    'reference_type' => 'donhang_seller',
                    'reference_id'   => $donHang->ID_DonHang,
                    'description'    => 'Doanh thu từ đơn hàng #' . $donHang->MaDonHangCon
                ]);

                // 2. Cộng ví Admin (Sàn)
                $adminUserId = 6; // Giả định ID admin là 6 theo AdminDonHangController
                $adminWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $adminUserId]);
                $adminWallet = \App\Models\Wallet::lockForUpdate()->find($adminWallet->id);

                $beforeAdmin = $adminWallet->balance;
                $adminWallet->balance += $commission;
                $adminWallet->save();

                \App\Models\WalletTransaction::create([
                    'wallet_id'      => $adminWallet->id,
                    'type'           => 'commission',
                    'status'         => 'completed',
                    'amount'         => $commission,
                    'balance_before' => $beforeAdmin,
                    'balance_after'  => $adminWallet->balance,
                    'reference_type' => 'donhang_admin',
                    'reference_id'   => $donHang->ID_DonHang,
                    'description'    => 'Hoa hồng 5% từ đơn hàng #' . $donHang->MaDonHangCon
                ]);

                // 3. Cập nhật bảng thanh toán nếu là đơn COD
                $donHangTong = $donHang->donHangTong;
                if ($donHangTong) {
                    $thanhToan = \App\Models\ThanhToan::where('ID_DonHangTong', $donHangTong->ID_DonHangTong)
                        ->where('PhuongThuc', 'COD')
                        ->first();

                    if ($thanhToan && $thanhToan->TrangThai == 0) {
                        $thanhToan->TrangThai = 1; // Đã thanh toán
                        $thanhToan->Date = now();
                        $thanhToan->save();

                        // Cập nhật luôn trạng thái ở Đơn Hàng Tổng
                        $donHangTong->TrangThaiThanhToan = \App\Models\DonHangTong::THANH_TOAN_DA_THANH_TOAN;
                        $donHangTong->save();
                    }
                }
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật trạng thái đơn hàng.',
                'data'    => $donHang
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Seller xem danh sách đơn hàng của Shop mình
     * GET /api/seller/orders
     */
    public function getOrders(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $shop = Shop::where('ID_User', $user->ID_User)->first();

            if (!$shop) {
                return response()->json(['success' => false, 'message' => 'Bạn chưa có gian hàng.'], 403);
            }

            $donHangs = \App\Models\DonHang::with(['chiTiet.sanPham', 'donHangTong', 'nguoiMua'])
                ->where('ID_Shop', $shop->ID_Shop)
                ->orderBy('ID_DonHang', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => $donHangs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải danh sách đơn hàng',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
