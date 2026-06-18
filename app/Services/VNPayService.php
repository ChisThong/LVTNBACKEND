<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * VNPayService — Xử lý tích hợp VNPay Sandbox
 *
 * Tài liệu tham chiếu chính thức: https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/
 */
class VNPayService
{
    protected string $tmnCode;
    protected string $hashSecret;
    protected string $vnpUrl;
    protected string $returnUrl;
    protected string $version;
    protected string $command;
    protected string $currencyCode;
    protected string $locale;
    protected string $orderType;
    protected string $frontendReturnUrl;

    public function __construct()
    {
        $this->tmnCode           = config('services.vnpay.tmn_code');
        $this->hashSecret        = config('services.vnpay.hash_secret');
        $this->vnpUrl            = config('services.vnpay.url');
        $this->returnUrl         = config('services.vnpay.return_url');
        $this->version           = config('services.vnpay.version', '2.1.0');
        $this->command           = config('services.vnpay.command', 'pay');
        $this->currencyCode      = config('services.vnpay.currency_code', 'VND');
        $this->locale            = config('services.vnpay.locale', 'vn');
        $this->orderType         = config('services.vnpay.order_type', 'other');
        $this->frontendReturnUrl = config('services.vnpay.frontend_return_url', 'http://localhost:5173/thanh-toan-thanh-cong');
    }

    // ══════════════════════════════════════════════════════════════════════
    // BƯỚC 3: Tạo URL thanh toán VNPay
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Tạo URL thanh toán VNPay đầy đủ để redirect người dùng.
     */
    public function createPaymentUrl(
        string $txnRef,
        int    $amount,
        string $orderInfo,
        string $ipAddr,
        int    $userId
    ): string {
        // VNPay yêu cầu amount × 100 (đơn vị: xu)
        $vnpAmount  = $amount * 100;
        $createDate = now('Asia/Ho_Chi_Minh')->format('YmdHis');
        $expireDate = now('Asia/Ho_Chi_Minh')->addMinutes(15)->format('YmdHis');

        // Tập hợp đầy đủ các tham số bắt buộc theo tài liệu VNPay 2.1.0
        $params = [
            'vnp_Version'    => $this->version,     // 2.1.0
            'vnp_Command'    => $this->command,     // pay
            'vnp_TmnCode'    => $this->tmnCode,     // Mã website
            'vnp_Amount'     => $vnpAmount,         // Số tiền × 100
            'vnp_CreateDate' => $createDate,        // yyyyMMddHHmmss
            'vnp_CurrCode'   => $this->currencyCode, // VND
            'vnp_IpAddr'     => $ipAddr,             // IP người dùng
            'vnp_Locale'     => $this->locale,       // vn | en
            'vnp_OrderInfo'  => $orderInfo,          // Nội dung đơn hàng
            'vnp_OrderType'  => $this->orderType,    // other
            'vnp_ReturnUrl'  => $this->returnUrl,    // Backend nhận redirect
            'vnp_TxnRef'     => $txnRef,             // Mã đơn hàng duy nhất
            'vnp_ExpireDate' => $expireDate,         // Hết hạn thanh toán
        ];

        // Bước 1: Sắp xếp theo thứ tự bảng chữ cái (ASCII ascending)
        ksort($params);

        // Bước 2: Tạo chuỗi dữ liệu băm (hashData) và chuỗi truy vấn (query) chuẩn urlencode
        $hashData = "";
        $query = "";
        $i = 0;

        foreach ($params as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Bước 3: Ký chữ ký số HMAC-SHA512
        $secureHash = hash_hmac('sha512', $hashData, $this->hashSecret);

        // Bước 4: Ghép thành URL hoàn chỉnh chuyển sang VNPay
        $paymentUrl = $this->vnpUrl . '?' . $query . 'vnp_SecureHash=' . $secureHash;

        Log::channel('vnpay')->info('[VNPayService][createPaymentUrl] URL generated', [
            'txnRef'     => $txnRef,
            'userId'     => $userId,
            'amount'     => $amount,
            'vnpAmount'  => $vnpAmount,
            'orderInfo'  => $orderInfo,
            'secureHash' => $secureHash,
        ]);

        return $paymentUrl;
    }

    // ══════════════════════════════════════════════════════════════════════
    // BƯỚC 4: Xác thực chữ ký khi kết quả trả về
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Xác thực chữ ký từ VNPay Return URL (GET — browser redirect).
     */
    public function verifyReturnHash(array $data): bool
    {
        return $this->verifyHash($data, 'ReturnUrl');
    }

    /**
     * Xác thực chữ ký từ VNPay IPN (POST — server-to-server).
     */
    public function verifyIpnHash(array $data): bool
    {
        return $this->verifyHash($data, 'IPN');
    }

    /**
     * Core kiểm tra chữ ký số đồng bộ cấu trúc urlencode
     */
    protected function verifyHash(array $data, string $context): bool
    {
        if (empty($data['vnp_SecureHash'])) {
            Log::channel('vnpay')->warning("[VNPayService][verify{$context}] Missing vnp_SecureHash", [
                'data' => array_keys($data),
            ]);
            return false;
        }

        $receivedHash = $data['vnp_SecureHash'];

        // Loại bỏ trường hash ra khỏi mảng dữ liệu để tính toán lại chữ ký gốc
        $params = $data;
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);

        // Sắp xếp theo alphabet
        ksort($params);

        // Duyệt mảng build chuỗi dữ liệu gốc giống luồng tạo link
        $hashData = "";
        $i = 0;
        foreach ($params as $key => $value) {
            if ($i == 1) {
                $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashData .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $expected = hash_hmac('sha512', $hashData, $this->hashSecret);
        $isValid = hash_equals($expected, $receivedHash);

        Log::channel('vnpay')->info("[VNPayService][verify{$context}]", [
            'txnRef'       => $data['vnp_TxnRef']       ?? 'N/A',
            'responseCode' => $data['vnp_ResponseCode'] ?? 'N/A',
            'expected'     => $expected,
            'received'     => $receivedHash,
            'valid'        => $isValid,
        ]);

        return $isValid;
    }

    /**
     * Trả về frontend URL để redirect sau khi xử lý xong.
     */
    public function getFrontendReturnUrl(): string
    {
        return $this->frontendReturnUrl;
    }
}