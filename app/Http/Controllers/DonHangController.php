<?php

namespace App\Http\Controllers;

use App\Http\Requests\DonHang\StoreDonHangRequest;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\Product;
use App\Models\DonHangTong;
use App\Services\VNPayService; // Đảm bảo bạn đã tạo file VNPayService
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonHangController extends Controller
{
    /**
     * Đặt hàng mới — hỗ trợ Multi-vendor và VNPay.
     * POST /api/don-hang
     */
    public function store(StoreDonHangRequest $request): JsonResponse
    {
        $user = $request->user();

        // ── 1. Khởi tạo Transaction ngay từ đầu ──────────────────────────────
        DB::beginTransaction();
        try {
            $sanPhamIds = collect($request->san_pham)->pluck('ID_SanPham')->toArray();
            
            // ── 2. Lấy sản phẩm & CHỐNG RACE CONDITION (Khóa bi quan) ────────
            $sanPhamList = Product::whereIn('ID_SanPham', $sanPhamIds)
                                ->where('TrangThai', 1)
                                ->lockForUpdate()
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
                    $loi[] = "Sản phẩm \"{$sp->TenSanPham}\" chỉ còn {$sp->SoLuongTon} sản phẩm, bạn đặt {$item['SoLuong']}.";
                }
            }

            if (! empty($loi)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không đủ hàng trong kho.',
                    'errors'  => $loi,
                ], 422);
            }

            // ── 3. Tính tổng tiền toàn bộ đơn hàng (tất cả các Shop) ──────────
            $tongTienToanBo = 0;
            foreach ($request->san_pham as $item) {
                $tongTienToanBo += $sanPhamList[$item['ID_SanPham']]->Gia * $item['SoLuong'];
            }

            // Lấy phương thức thanh toán động từ Frontend gửi lên ('COD' hoặc 'VNPAY' hoặc 'WALLET')
            $phuongThuc = $request->input('PhuongThucThanhToan', 'COD');

            // ── 3.5. Xử lý logic thanh toán bằng Ví (WALLET) ───────────────
            if ($phuongThuc === 'WALLET') {
                $wallet = DB::table('wallets')->where('user_id', $user->ID_User)->lockForUpdate()->first();
                
                if (!$wallet || $wallet->balance < $tongTienToanBo) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Số dư ví không đủ, vui lòng nạp thêm tiền.'
                    ], 422);
                }

                // Trừ tiền trong ví
                DB::table('wallets')->where('id', $wallet->id)->decrement('balance', $tongTienToanBo);

                // Lưu vết giao dịch
                DB::table('wallet_transactions')->insert([
                    'wallet_id'   => $wallet->id,
                    'amount'      => -$tongTienToanBo,
                    'type'        => 'expense', // Chi tiêu mua hàng
                    'description' => 'Thanh toán đơn hàng qua Ví',
                    'status'      => 'completed',
                    'created_at'  => now(),
                    'updated_at'  => now()
                ]);
            }

            // ── 4. Tạo bản ghi DonHangTong ─────────────────────────────────────
            $donHangTong = DonHangTong::create([
                'ID_User'             => $user->ID_User,
                'NguoiNhan'           => $user->HoTen ?? 'Khách hàng',
                'SDTNhan'             => $request->SDTNhanHang,
                'DiaChiNhan'          => $request->DiaChiGiao,
                'TongGiaTien'         => $tongTienToanBo,
                'PhuongThucThanhToan' => $phuongThuc,
                'TrangThaiThanhToan'  => $phuongThuc === 'WALLET' ? DonHangTong::THANH_TOAN_DA_THANH_TOAN : DonHangTong::THANH_TOAN_CHUA_THANH_TOAN,
                'Ngaydat'             => now(),
            ]);
            

            // ── 5. Gom nhóm sản phẩm theo Shop (Group by ID_Shop) ──────────────
            $itemsByShop = [];
            foreach ($request->san_pham as $item) {
                $sp = $sanPhamList[$item['ID_SanPham']];
                $shopId = $sp->ID_Shop;
                
                if (!isset($itemsByShop[$shopId])) {
                    $itemsByShop[$shopId] = [];
                }
                
                $item['ProductModel'] = $sp;
                $itemsByShop[$shopId][] = $item;
            }

            // ── 6. Lặp qua từng nhóm Shop để tạo đơn con và chi tiết ──────────
            $createdSubOrders = [];
            foreach ($itemsByShop as $shopId => $items) {
                $tongTienShop = 0;
                foreach ($items as $item) {
                    $tongTienShop += $item['ProductModel']->Gia * $item['SoLuong'];
                }

                // Tạo DonHang con cho Shop này
                $donHangCon = DonHang::create([
                    'ID_DonHangTong' => $donHangTong->ID_DonHangTong,
                    'MaDonHangCon'   => 'DHC_' . strtoupper(uniqid()),
                    'ID_Shop'        => $shopId,
                    'ID_User'        => $user->ID_User,
                    'TongGia'        => $tongTienShop,
                    'PhiVanChuyen'   => 0,
                    'TrangThai'      => DonHang::TRANG_THAI_CHO_XAC_NHAN,
                    'MaVanDon'       => null,
                    'date'           => now(),
                ]);
                $createdSubOrders[] = $donHangCon;

                // Tạo ChiTietDonHang và Trừ Kho
                foreach ($items as $item) {
                    $sp = $item['ProductModel'];

                    ChiTietDonHang::create([
                        'ID_DonHang' => $donHangCon->ID_DonHang,
                        'ID_SanPham' => $sp->ID_SanPham,
                        'SoLuong'    => $item['SoLuong'],
                        'TongGia'    => $sp->Gia * $item['SoLuong'],
                    ]);

                    // Trừ tồn kho
                    $sp->decrement('SoLuongTon', $item['SoLuong']);
                }
            }

            // ── 7. Dọn dẹp Giỏ Hàng (ChiTietGioHang) ──────────────────────────
            $gioHang = DB::table('giohang')->where('ID_User', $user->ID_User)->first();
            if ($gioHang) {
                DB::table('chitietgiohang')
                    ->where('ID_GioHang', $gioHang->ID_GioHang)
                    ->whereIn('ID_SanPham', $sanPhamIds)
                    ->delete();
            }

            // ── 8. XỬ LÝ KHỚP NỐI VNPAY ────────────────────────────────────────
            $vnpayUrl = null;
            if ($phuongThuc === 'VNPAY') {
                // Khởi tạo VNPayService từ container để lấy URL thanh toán
                $vnpayService = app(\App\Services\VNPayService::class);
                // Giả định hàm generateUrl lấy theo ID đơn hàng
                if (method_exists($vnpayService, 'generateUrl')) {
                    $vnpayUrl = $vnpayService->generateUrl($donHangTong->ID_DonHangTong);
                } else if (method_exists($vnpayService, 'createPaymentUrl')) {
                    $vnpayUrl = $vnpayService->createPaymentUrl(
                        $donHangTong->ID_DonHangTong, 
                        $tongTienToanBo, 
                        "Thanh toan don hang {$donHangTong->ID_DonHangTong}", 
                        $request->ip(), 
                        $user->ID_User
                    );
                }
                
                DB::commit();

                //thong báo
                foreach ($createdSubOrders as $subOrder) {
                    $activityData = [
                        'id_target' => $subOrder->ID_DonHang,
                        'tieude' => "Bạn có một đơn hàng mới #" . $subOrder->MaDonHangCon . " đang chờ xác nhận.",
                        'thoigian' => now()->toDateTimeString(),
                        'trangthai' => 'Mới',
                        'type' => 'order'
                    ];
                    event(new \App\Events\SellerActivityEvent($activityData, $subOrder->ID_Shop));
                }

                return response()->json([
                    'success'   => true,
                    'message'   => 'Tạo đơn hàng thành công, đang chuyển hướng VNPay...',
                    'vnpay_url' => $vnpayUrl
                ], 201);
            }
            // ── 9. LƯU LỊCH SỬ THANH TOÁN CHO COD ─────────────────────────────────
            if ($phuongThuc === 'COD') {
                \App\Models\ThanhToan::create([
                    'PhuongThuc'     => 'COD',
                    'code_GiaoDich'  => 'COD_' . time() . '_' . $donHangTong->ID_DonHangTong,
                    'SoTien'         => $tongTienToanBo,
                    'TrangThai'      => 0, // Chưa thanh toán
                    'Date'           => now(),
                    'ID_DonHangTong' => $donHangTong->ID_DonHangTong
                ]);
            }

            // Nếu là WALLET hoặc COD thì không cần chuyển hướng VNPay
            DB::commit();

            // Dispatch real-time notification events
            foreach ($createdSubOrders as $subOrder) {
                $activityData = [
                    'id_target' => $subOrder->ID_DonHang,
                    'tieude' => "Bạn có một đơn hàng mới #" . $subOrder->MaDonHangCon . " đang chờ xác nhận.",
                    'thoigian' => now()->toDateTimeString(),
                    'trangthai' => 'Mới',
                    'type' => 'order'
                ];
                event(new \App\Events\SellerActivityEvent($activityData, $subOrder->ID_Shop));
            }

            return response()->json([
                'success'   => true,
                'message'   => 'Đặt hàng thành công.',
                'vnpay_url' => null
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Danh sách đơn mua của Khách hàng (Nhóm theo Đơn Hàng Tổng).
     * GET /api/don-hang
     */
    public function index(Request $request): JsonResponse
    {
        // Lấy theo đơn hàng tổng để đúng cấu trúc hiển thị đa gian hàng
        $donHangTong = DonHangTong::with('donHangs.shop', 'donHangs.chiTiet.sanPham.hinhAnh', 'donHangs.chiTiet.danhGia')
            ->where('ID_User', $request->user()->ID_User)
            ->orderByDesc('ID_DonHangTong')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $donHangTong,
        ]);
    }

    /**
     * Chi tiết 1 đơn hàng tổng.
     * GET /api/don-hang/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $donHangTong = DonHangTong::with('donHangs.shop', 'donHangs.chiTiet.sanPham.hinhAnh', 'donHangs.chiTiet.danhGia')
            ->where('ID_DonHangTong', $id)
            ->where('ID_User', $request->user()->ID_User)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $donHangTong,
        ]);
    }

    /**
     * IPN Callback xử lý thanh toán VNPay cho Đơn hàng.
     * POST /api/vnpay/order-ipn
     */
    public function vnpayIpn(Request $request): JsonResponse
    {
        $data = $request->all();
        $vnpayService = app(\App\Services\VNPayService::class);

        \Illuminate\Support\Facades\Log::channel('vnpay')->info('[DonHangController][vnpayIpn] Received IPN for Order', $data);

        // 1. Kiểm tra chữ ký số
        if (empty($data['vnp_SecureHash'])) {
            return response()->json(['RspCode' => '97', 'Message' => 'Missing SecureHash']);
        }

        if (!$vnpayService->verifyIpnHash($data)) {
            \Illuminate\Support\Facades\Log::channel('vnpay')->error('[DonHangController][vnpayIpn] Invalid SecureHash', ['txnRef' => $data['vnp_TxnRef'] ?? 'N/A']);
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid Signature']);
        }

        $txnRef = $data['vnp_TxnRef'] ?? null;
        $responseCode = $data['vnp_ResponseCode'] ?? '-1';

        if (!$txnRef) {
            return response()->json(['RspCode' => '01', 'Message' => 'Order Not Found']);
        }

        // Bóc tách ID Đơn Hàng Tổng (vnp_TxnRef = ID_DonHangTong)
        $idDonHangTong = $txnRef;

        try {
            DB::beginTransaction();

            // 2. Tìm kiếm và khóa dòng dữ liệu chống Race Condition
            $donHangTong = DonHangTong::where('ID_DonHangTong', $idDonHangTong)->lockForUpdate()->first();

            if (!$donHangTong) {
                DB::rollBack();
                return response()->json(['RspCode' => '01', 'Message' => 'Order Not Found']);
            }

            // Nếu đơn hàng đã được xác nhận thành công trước đó (Idempotent)
            if ($donHangTong->TrangThaiThanhToan == DonHangTong::THANH_TOAN_DA_THANH_TOAN) {
                DB::rollBack();
                return response()->json(['RspCode' => '02', 'Message' => 'Order Already Confirmed']);
            }

            // 3. Logic xử lý trạng thái
            if ($responseCode === '00') {
                // Thành công
                $donHangTong->TrangThaiThanhToan = DonHangTong::THANH_TOAN_DA_THANH_TOAN;
                $donHangTong->PhuongThucThanhToan = 'VNPAY';
                $donHangTong->save();

                \Illuminate\Support\Facades\Log::channel('vnpay')->info('[DonHangController][vnpayIpn] SUCCESS — Order payment confirmed', ['txnRef' => $txnRef]);
            } else {
                // Thất bại hoặc Hủy
                $donHangTong->TrangThaiThanhToan = 2; // 2: Thanh toán thất bại
                $donHangTong->PhuongThucThanhToan = 'VNPAY';
                $donHangTong->save();

                // Lấy các đơn hàng con
                $donHangCons = DonHang::where('ID_DonHangTong', $idDonHangTong)->get();

                foreach ($donHangCons as $donHangCon) {
                    // Chuyển trạng thái đơn con sang HỦY (4)
                    $donHangCon->TrangThai = 4;
                    $donHangCon->save();

                    // Lấy chi tiết đơn hàng con
                    $chiTiets = DB::table('chitietdonhang')->where('ID_DonHang', $donHangCon->ID_DonHang)->get();

                    foreach ($chiTiets as $chiTiet) {
                        // Hoàn lại tồn kho cho từng sản phẩm
                        $product = Product::find($chiTiet->ID_SanPham);
                        if ($product) {
                            $product->increment('SoLuongTon', $chiTiet->SoLuong);
                        }
                    }
                }

                \Illuminate\Support\Facades\Log::channel('vnpay')->info('[DonHangController][vnpayIpn] FAILED — Order cancelled and inventory restored', ['txnRef' => $txnRef, 'responseCode' => $responseCode]);
            }

            // 4. Commit và trả về kết quả chuẩn
            DB::commit();

            return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::channel('vnpay')->error('[DonHangController][vnpayIpn] Exception', [
                'txnRef' => $txnRef,
                'error'  => $e->getMessage(),
            ]);

            return response()->json(['RspCode' => '99', 'Message' => 'Unknown Error']);
        }
    }

    /**
     * Người mua hủy đơn hàng con.
     * PUT /api/orders/{id}/cancel
     */
    public function huyDonHang(Request $request, $idDonHang): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            // 1. Khóa và lấy thông tin Đơn hàng con
            $donHang = DonHang::with('donHangTong')
                ->where('ID_DonHang', $idDonHang)
                ->where('ID_User', $user->ID_User)
                ->lockForUpdate()
                ->first();

            if (!$donHang) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng'
                ], 404);
            }

            // 2. Validate trạng thái (Chỉ cho hủy nếu đang chờ xác nhận)
            if ($donHang->TrangThai != DonHang::TRANG_THAI_CHO_XAC_NHAN) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn hàng đã được Shop xử lý, không thể hủy.'
                ], 400);
            }

            // 3. Cập nhật trạng thái Hủy đơn
            $donHang->TrangThai = DonHang::TRANG_THAI_HUY;
            $donHang->save();

            // 4. Hoàn tồn kho
            $chiTiets = DB::table('chitietdonhang')->where('ID_DonHang', $donHang->ID_DonHang)->get();
            foreach ($chiTiets as $chiTiet) {
                $product = Product::find($chiTiet->ID_SanPham);
                if ($product) {
                    $product->increment('SoLuongTon', $chiTiet->SoLuong);
                }
            }

            // 5. Kiểm tra và Hoàn tiền vào Ví (Nếu đã thanh toán trả trước)
            $donHangTong = $donHang->donHangTong;
            if ($donHangTong && $donHangTong->TrangThaiThanhToan == DonHangTong::THANH_TOAN_DA_THANH_TOAN) {
                $tienHoan = $donHang->TongGia + $donHang->PhiVanChuyen;

                // Lấy ví của user (Lock để an toàn)
                $wallet = DB::table('wallets')->where('user_id', $user->ID_User)->lockForUpdate()->first();
                
                if ($wallet) {
                    // Cộng tiền
                    DB::table('wallets')->where('id', $wallet->id)->increment('balance', $tienHoan);

                    // Ghi log hoàn tiền
                    DB::table('wallet_transactions')->insert([
                        'wallet_id'   => $wallet->id,
                        'amount'      => $tienHoan,
                        'type'        => 'refund', // Loại hoàn tiền
                        'description' => 'Hoàn tiền do hủy đơn hàng con #' . $donHang->MaDonHangCon,
                        'status'      => 'completed',
                        'created_at'  => now(),
                        'updated_at'  => now()
                    ]);
                }
            }

            DB::commit();

            $activityData = [
                'id_target' => $donHang->ID_DonHang,
                'tieude' => "Đơn hàng #" . $donHang->MaDonHangCon . " đã bị khách hàng hủy.",
                'thoigian' => now()->toDateTimeString(),
                'trangthai' => 'Mới',
                'type' => 'order'
            ];
            event(new \App\Events\SellerActivityEvent($activityData, $donHang->ID_Shop));

            return response()->json([
                'success' => true,
                'message' => 'Đã hủy đơn hàng thành công.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi hủy đơn.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Người mua xác nhận đã nhận được hàng.
     * PUT /api/don-hang/{id}/confirm-received
     *
     * Logic:
     *  - Đơn phải đang ở trạng thái 2 (Đang giao).
     *  - Chuyển sang 3 (Hoàn tất).
     *  - Chia tiền tự động: 95% → Ví Seller, 5% → Ví Admin (hoa hồng sàn).
     */
    public function xacNhanNhanHang(Request $request, $idDonHang): JsonResponse
    {
        $user = $request->user();

        try {
            DB::beginTransaction();

            // 1. Tìm và khóa đơn hàng con (chống race condition)
            $donHang = DonHang::with(['shop', 'donHangTong'])
                ->where('ID_DonHang', $idDonHang)
                ->where('ID_User', $user->ID_User)
                ->lockForUpdate()
                ->first();

            if (!$donHang) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy đơn hàng.'
                ], 404);
            }

            // 2. Chỉ cho phép xác nhận khi đơn đang ở trạng thái "Đang giao" (2)
            if ($donHang->TrangThai !== DonHang::TRANG_THAI_DANG_GIAO) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ có thể xác nhận khi đơn hàng đang ở trạng thái "Đang giao".'
                ], 400);
            }

            // 3. Chuyển trạng thái sang Hoàn tất (3)
            $donHang->TrangThai = DonHang::TRANG_THAI_HOAN_TAT;
            $donHang->save();

            // ── 4. KÍCH HOẠT LUỒNG CHIA TIỀN TỰ ĐỘNG ──────────────────────────
            $tongGia    = $donHang->TongGia + $donHang->PhiVanChuyen;
            $commission = $tongGia * 0.05;       // 5%  → Admin (hoa hồng sàn)
            $sellerAmt  = $tongGia - $commission; // 95% → Seller

            $adminUserId  = 6; // ID Admin cố định (đồng bộ với AdminDonHangController)
            $sellerUserId = $donHang->shop?->ID_User;

            if ($sellerUserId) {
                // 4a. Cộng 95% vào Ví Seller
                $sellerWallet = \App\Models\Wallet::firstOrCreate(['user_id' => $sellerUserId]);
                $sellerWallet = \App\Models\Wallet::lockForUpdate()->find($sellerWallet->id);

                $beforeSeller = $sellerWallet->balance;
                $sellerWallet->balance += $sellerAmt;
                $sellerWallet->save();

                \App\Models\WalletTransaction::create([
                    'wallet_id'      => $sellerWallet->id,
                    'type'           => 'revenue',
                    'status'         => 'completed',
                    'amount'         => $sellerAmt,
                    'balance_before' => $beforeSeller,
                    'balance_after'  => $sellerWallet->balance,
                    'reference_type' => 'donhang_seller',
                    'reference_id'   => $donHang->ID_DonHang,
                    'description'    => 'Doanh thu từ đơn hàng #' . $donHang->MaDonHangCon . ' (Người mua xác nhận)',
                ]);
            }

            // 4b. Cộng 5% hoa hồng vào Ví Admin
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
                'description'    => 'Hoa hồng 5% từ đơn hàng #' . $donHang->MaDonHangCon,
            ]);

            // 4c. Nếu là COD: cập nhật trạng thái thanh toán → Đã thanh toán
            $donHangTong = $donHang->donHangTong;
            if ($donHangTong) {
                $thanhToan = \App\Models\ThanhToan::where('ID_DonHangTong', $donHangTong->ID_DonHangTong)
                    ->where('PhuongThuc', 'COD')
                    ->first();

                if ($thanhToan && $thanhToan->TrangThai == 0) {
                    $thanhToan->TrangThai = 1; // Đã thanh toán
                    $thanhToan->Date = now();
                    $thanhToan->save();

                    $donHangTong->TrangThaiThanhToan = DonHangTong::THANH_TOAN_DA_THANH_TOAN;
                    $donHangTong->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xác nhận nhận hàng thành công! Cảm ơn bạn đã mua sắm.',
                'data'    => $donHang->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xác nhận nhận hàng.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}