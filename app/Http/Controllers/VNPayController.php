<?php

namespace App\Http\Controllers;

use App\Services\VNPayService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * VNPayController — Xử lý tích hợp thanh toán VNPay Sandbox
 *
 * Routes:
 *  POST  /api/vnpay/create-payment  [auth:sanctum, throttle:5,1]  → Tạo URL thanh toán
 *  GET   /vnpay-return               [web, public]                 → VNPay redirect trình duyệt về
 *  GET   /api/vnpay/return           [api, public]                 → Alias (giữ backward compat)
 *  POST  /api/vnpay/ipn              [api, public]                 → VNPay IPN server-to-server
 */
class VNPayController extends Controller
{
    protected VNPayService  $vnpayService;
    protected WalletService $walletService;

    public function __construct(VNPayService $vnpayService, WalletService $walletService)
    {
        $this->vnpayService  = $vnpayService;
        $this->walletService = $walletService;
    }

    // ══════════════════════════════════════════════════════════════════════
    // BƯỚC 3: Tạo URL thanh toán — redirect user sang VNPay
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/vnpay/create-payment
     *
     * 1. Validate amount
     * 2. Tạo mã giao dịch duy nhất (txnRef)
     * 3. Lưu giao dịch PENDING vào DB
     * 4. Gọi VNPayService::createPaymentUrl()
     * 5. Trả về payUrl cho frontend redirect
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:10000|max:50000000',
        ]);

        $user   = Auth::user();
        $userId = $user->ID_User ?? $user->id;
        $amount = (int) $request->amount;

        // Tạo mã giao dịch duy nhất — định dạng: VNPAY_<timestamp>_<userId>_<random6>
        $txnRef = 'VNPAY' . time() . $userId . strtoupper(bin2hex(random_bytes(3)));

        // Lấy IP client (vnp_IpAddr — bắt buộc)
        $ipAddr    = $request->ip() ?: '127.0.0.1';
        $orderInfo = 'Nap tien vi user ' . $userId;

        Log::channel('vnpay')->info('[VNPayController][createPayment] Initiating', [
            'userId'    => $userId,
            'amount'    => $amount,
            'txnRef'    => $txnRef,
            'ipAddr'    => $ipAddr,
            'orderInfo' => $orderInfo,
        ]);

        try {
            // Lưu giao dịch PENDING trước khi redirect (an toàn nếu user đóng browser)
            $this->walletService->createPendingTransaction(
                $userId,
                $amount,
                'vnpay',
                $txnRef
            );

            // Tạo URL thanh toán VNPay
            $payUrl = $this->vnpayService->createPaymentUrl(
                $txnRef,
                $amount,
                $orderInfo,
                $ipAddr,
                $userId
            );

            return response()->json([
                'status' => 'success',
                'payUrl' => $payUrl,
                'txnRef' => $txnRef,
            ]);

        } catch (\Exception $e) {
            // Đánh fail giao dịch nếu có lỗi trước khi redirect
            $this->walletService->failTransaction('vnpay', $txnRef);

            Log::channel('vnpay')->error('[VNPayController][createPayment] Exception', [
                'txnRef' => $txnRef,
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Lỗi hệ thống khi khởi tạo thanh toán. Vui lòng thử lại.',
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /vnpay-return — Nhận redirect từ VNPay qua web route (ngrok)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /vnpay-return
     *
     * VNPay redirect trình duyệt người dùng về đây sau khi thanh toán.
     * Route này nằm trong web.php (không có prefix /api).
     *
     * Logic bảo mật:
     *  1. Xác thực chữ ký vnp_SecureHash
     *  2. Kiểm tra vnp_ResponseCode == '00' → thành công
     *  3. Cộng tiền vào ví (idempotent, IPN có thể đã xử lý trước)
     *  4. Redirect về React frontend kèm status + txn_ref
     */
    public function vnpayReturn(Request $request)
    {
        $data         = $request->all();
        $txnRef       = $data['vnp_TxnRef']       ?? '';
        $responseCode = $data['vnp_ResponseCode']  ?? '-1';
        $vnpAmount    = (int) ($data['vnp_Amount'] ?? 0);
        $amount       = $vnpAmount / 100; // VNPay gửi amount × 100

        $frontendBase = $this->vnpayService->getFrontendReturnUrl();

        Log::channel('vnpay')->info('[VNPayController][vnpayReturn] Received', [
            'txnRef'       => $txnRef,
            'responseCode' => $responseCode,
            'amount'       => $amount,
        ]);

        // Bước 1: Xác thực chữ ký bảo mật
        if (!$this->vnpayService->verifyReturnHash($data)) {
            Log::channel('vnpay')->error('[VNPayController][vnpayReturn] Invalid SecureHash', [
                'txnRef' => $txnRef,
            ]);
            return redirect()->to($frontendBase . '?status=failed&reason=invalid_signature&txn_ref=' . urlencode($txnRef));
        }

        // Bước 2: Kiểm tra kết quả giao dịch
        if ($responseCode === '00') {
            try {
                // Bước 3: Cộng tiền vào ví (idempotent — WalletService tự lock)
                $this->walletService->confirmDeposit('vnpay', $txnRef, $amount);

                Log::channel('vnpay')->info('[VNPayController][vnpayReturn] Deposit confirmed', [
                    'txnRef' => $txnRef,
                    'amount' => $amount,
                ]);
            } catch (\Exception $e) {
                // IPN đã xử lý trước → không phải lỗi, bỏ qua
                Log::channel('vnpay')->info('[VNPayController][vnpayReturn] Already processed (IPN done first)', [
                    'txnRef' => $txnRef,
                    'info'   => $e->getMessage(),
                ]);
            }

            // Bước 4: Redirect thành công về React
            return redirect()->to(
                $frontendBase
                . '?status=success'
                . '&txn_ref=' . urlencode($txnRef)
                . '&amount='  . $amount
            );
        }

        // Thanh toán thất bại hoặc bị hủy
        try {
            $this->walletService->failTransaction('vnpay', $txnRef);
        } catch (\Exception $e) {
            Log::channel('vnpay')->warning('[VNPayController][vnpayReturn] failTransaction warning', [
                'txnRef' => $txnRef,
                'info'   => $e->getMessage(),
            ]);
        }

        Log::channel('vnpay')->info('[VNPayController][vnpayReturn] Payment failed/cancelled', [
            'txnRef'       => $txnRef,
            'responseCode' => $responseCode,
        ]);

        return redirect()->to(
            $frontendBase
            . '?status=failed'
            . '&responseCode=' . urlencode($responseCode)
            . '&txn_ref='      . urlencode($txnRef)
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // GET /api/vnpay/return — Alias giữ backward compat (dùng cùng logic)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * GET /api/vnpay/return — Alias backward-compat, gọi lại vnpayReturn().
     */
    public function returnUrl(Request $request)
    {
        $data         = $request->all();
        $txnRef       = $data['vnp_TxnRef']        ?? '';
        $responseCode = $data['vnp_ResponseCode']  ?? '-1';
        $vnpAmount    = (int) ($data['vnp_Amount'] ?? 0);
        $amount       = $vnpAmount / 100; // VNPay gửi amount × 100

        $frontendBase = $this->vnpayService->getFrontendReturnUrl();

        Log::channel('vnpay')->info('[VNPayController][returnUrl] Received', [
            'txnRef'       => $txnRef,
            'responseCode' => $responseCode,
            'amount'       => $amount,
            'allParams'    => array_keys($data),
        ]);

        // Bước bảo mật 1: Kiểm tra chữ ký
        if (!$this->vnpayService->verifyReturnHash($data)) {
            Log::channel('vnpay')->error('[VNPayController][returnUrl] Invalid SecureHash', [
                'txnRef' => $txnRef,
            ]);
            return redirect()->away(
                $frontendBase . '?status=failed&reason=invalid_signature&txnRef=' . urlencode($txnRef)
            );
        }

        // Bước bảo mật 2: Kiểm tra ResponseCode
        if ($responseCode === '00') {
            try {
                // Cộng tiền vào ví (idempotent — WalletService tự lock)
                $this->walletService->confirmDeposit('vnpay', $txnRef, $amount);

                Log::channel('vnpay')->info('[VNPayController][returnUrl] Deposit confirmed', [
                    'txnRef' => $txnRef,
                    'amount' => $amount,
                ]);
            } catch (\Exception $e) {
                // Giao dịch đã được xử lý bởi IPN trước đó → bỏ qua
                Log::channel('vnpay')->info('[VNPayController][returnUrl] confirmDeposit skipped (already processed via IPN)', [
                    'txnRef' => $txnRef,
                    'reason' => $e->getMessage(),
                ]);
            }

            return redirect()->away(
                $frontendBase
                . '?status=success'
                . '&txnRef='  . urlencode($txnRef)
                . '&amount='  . $amount
            );
        }

        // Thanh toán thất bại hoặc bị hủy
        try {
            $this->walletService->failTransaction('vnpay', $txnRef);
        } catch (\Exception $e) {
            Log::channel('vnpay')->warning('[VNPayController][returnUrl] failTransaction warning', [
                'txnRef' => $txnRef,
                'reason' => $e->getMessage(),
            ]);
        }

        Log::channel('vnpay')->info('[VNPayController][returnUrl] Payment failed', [
            'txnRef'       => $txnRef,
            'responseCode' => $responseCode,
        ]);

        return redirect()->away(
            $frontendBase
            . '?status=failed'
            . '&responseCode=' . urlencode($responseCode)
            . '&txnRef='       . urlencode($txnRef)
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // BƯỚC 4 (IPN): Xử lý IPN server-to-server từ VNPay (quan trọng nhất)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * POST /api/vnpay/ipn
     *
     * VNPay gọi server-to-server về đây (không qua browser, không phụ thuộc user).
     * Đây là cơ chế đảm bảo chính — phải xử lý đúng và trả JSON chuẩn VNPay.
     *
     * Kiểm tra bảo mật (theo thứ tự — theo tài liệu VNPay):
     *  1. Kiểm tra vnp_SecureHash hợp lệ
     *  2. Kiểm tra đơn hàng tồn tại (vnp_TxnRef)
     *  3. Kiểm tra số tiền khớp (vnp_Amount / 100 == DB amount)
     *  4. Kiểm tra trạng thái đơn chưa xử lý (chỉ cập nhật nếu đang PENDING)
     *  5. Nếu vnp_ResponseCode == "00" → cập nhật THÀNH CÔNG, cộng tiền vào ví
     *
     * Response format chuẩn VNPay:
     *  {"RspCode": "00", "Message": "Confirm Success"}
     */
    public function ipn(Request $request)
    {
        $data = $request->all();

        Log::channel('vnpay')->info('[VNPayController][ipn] Received IPN', [
            'txnRef'       => $data['vnp_TxnRef']       ?? 'N/A',
            'responseCode' => $data['vnp_ResponseCode'] ?? 'N/A',
            'amount'       => $data['vnp_Amount']        ?? 'N/A',
            'transNo'      => $data['vnp_TransactionNo'] ?? 'N/A',
        ]);

        // ── Kiểm tra bảo mật 1: vnp_SecureHash ──────────────────────────
        if (empty($data['vnp_SecureHash'])) {
            Log::channel('vnpay')->warning('[VNPayController][ipn] Missing vnp_SecureHash');
            return response()->json(['RspCode' => '97', 'Message' => 'Missing SecureHash']);
        }

        if (!$this->vnpayService->verifyIpnHash($data)) {
            Log::channel('vnpay')->error('[VNPayController][ipn] Invalid SecureHash', [
                'txnRef' => $data['vnp_TxnRef'] ?? 'N/A',
            ]);
            return response()->json(['RspCode' => '97', 'Message' => 'Invalid Signature']);
        }

        // ── Extract params ────────────────────────────────────────────────
        $txnRef       = $data['vnp_TxnRef']        ?? null;
        $responseCode = $data['vnp_ResponseCode']  ?? '-1';
        $vnpAmount    = (int) ($data['vnp_Amount'] ?? 0);
        $amount       = $vnpAmount / 100;

        // ── Kiểm tra bảo mật 2: Đơn hàng tồn tại ────────────────────────
        if (!$txnRef) {
            Log::channel('vnpay')->error('[VNPayController][ipn] Missing vnp_TxnRef');
            return response()->json(['RspCode' => '01', 'Message' => 'Order Not Found']);
        }

        $isSuccess = ($responseCode === '00');

        try {
            if ($isSuccess) {
                // ── Kiểm tra bảo mật 3 & 4: Amount + Status trong confirmDeposit ──
                // WalletService::confirmDeposit() tự kiểm tra:
                //  - Giao dịch tồn tại (→ RspCode 01 nếu không)
                //  - Amount khớp với DB (→ exception nếu sai)
                //  - Status là 'pending' (idempotent nếu đã xử lý → return sớm)
                $this->walletService->confirmDeposit('vnpay', $txnRef, $amount);

                Log::channel('vnpay')->info('[VNPayController][ipn] SUCCESS — Deposit confirmed', [
                    'txnRef'       => $txnRef,
                    'amount'       => $amount,
                    'responseCode' => $responseCode,
                    'transNo'      => $data['vnp_TransactionNo'] ?? 'N/A',
                ]);

                // VNPay yêu cầu trả {"RspCode":"00","Message":"Confirm Success"}
                return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);

            } else {
                // Thanh toán thất bại — đánh fail giao dịch
                $this->walletService->failTransaction('vnpay', $txnRef);

                Log::channel('vnpay')->info('[VNPayController][ipn] FAILED — Transaction marked failed', [
                    'txnRef'       => $txnRef,
                    'responseCode' => $responseCode,
                ]);

                return response()->json(['RspCode' => '00', 'Message' => 'Confirm Success']);
            }

        } catch (\Exception $e) {
            $message = $e->getMessage();

            Log::channel('vnpay')->error('[VNPayController][ipn] Exception', [
                'txnRef'  => $txnRef,
                'error'   => $message,
            ]);

            // Idempotent: đã xử lý thành công trước đó
            if (str_contains($message, 'success')) {
                return response()->json(['RspCode' => '02', 'Message' => 'Order Already Confirmed']);
            }

            // Amount mismatch
            if (str_contains($message, 'không khớp') || str_contains($message, 'mismatch')) {
                return response()->json(['RspCode' => '04', 'Message' => 'Invalid Amount']);
            }

            // Đơn hàng không tồn tại
            if (str_contains($message, 'không tìm thấy') || str_contains($message, 'not found')) {
                return response()->json(['RspCode' => '01', 'Message' => 'Order Not Found']);
            }

            return response()->json(['RspCode' => '99', 'Message' => 'Unknown Error']);
        }
    }
}
