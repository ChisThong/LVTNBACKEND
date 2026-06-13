<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Mail\SendOtpMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    //  Helper: sinh OTP và ghi vào bảng email_verifications
    // ──────────────────────────────────────────────────────────────────────
    private function generateAndSendOtp(User $user): void
    {
        // Hủy tất cả OTP cũ chưa dùng của email này
        EmailVerification::where('email', $user->email)
            ->where('is_used', false)
            ->delete();

        $otp = (string) random_int(100000, 999999);
EmailVerification::create([
    'email'       => $user->email,
    'otp_code'    => $otp,
    'created_at'  => now(), // Ép cột tạo mới về giờ VN
    'updated_at'  => now(), // Ép cột cập nhật về giờ VN
    'expires_at'  => now()->addMinutes(5),
    'is_used'     => false,
]);

        Mail::to($user->email)->send(new SendOtpMail($user->HoTen, $otp));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/register
    // ──────────────────────────────────────────────────────────────────────
    /**
     * Đăng ký tài khoản mới.
     * User được tạo với TrangThai = 0 (chưa xác thực).
     * Sau đó sinh OTP và gửi email.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'HoTen'      => $request->HoTen,
            'email'      => $request->email,
            'matkhau'    => Hash::make($request->matkhau),
            'diachi'     => $request->diachi,
            'sdt'        => $request->sdt,
            'TrangThai'  => 0,          // Chưa xác thực email
            // Ép buộc lấy giờ Việt Nam hiện tại cho ngày đăng ký
            'ngaydangki' => now(),
            'ID_role'    => $request->ID_role,
        ]);

        $this->generateAndSendOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để lấy mã OTP xác thực.',
            'data'    => [
                'email' => $user->email,
            ],
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/verify-otp
    // ──────────────────────────────────────────────────────────────────────
    /**
     * Xác thực OTP — kích hoạt tài khoản.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $record = EmailVerification::where('email', $request->email)
            ->where('otp_code', $request->otp_code)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Mã OTP không đúng.',
            ], 422);
        }

        // Ép thời gian so sánh hiện tại về cùng múi giờ Việt Nam để check hết hạn chính xác
        if ($record->expires_at->isBefore(now())) {
            return response()->json([
                'success' => false,
                'message' => 'Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.',
            ], 422);
        }

        // Đánh dấu OTP đã dùng
        $record->update(['is_used' => true]);

        // Kích hoạt tài khoản
        $user = User::where('email', $request->email)->first();
        $user->update(['TrangThai' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Xác thực email thành công. Bạn có thể đăng nhập ngay bây giờ.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/resend-otp
    // ──────────────────────────────────────────────────────────────────────
    /**
     * Gửi lại OTP — hủy OTP cũ, sinh mới, gửi email mới.
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ((int) $user->TrangThai === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản này đã được xác thực.',
            ], 422);
        }

        $this->generateAndSendOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi lại mã OTP. Vui lòng kiểm tra email.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/login
    // ──────────────────────────────────────────────────────────────────────
    /**
     * Đăng nhập — kiểm tra email + mật khẩu bằng cột matkhau.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->matkhau, $user->matkhau)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        // Chưa xác thực email
        if ((int) $user->TrangThai === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản chưa xác thực email. Vui lòng kiểm tra hộp thư và nhập mã OTP.',
                'data'    => ['email' => $user->email],
            ], 403);
        }

        // Xoá token cũ (single-session)
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'data'    => [
                'user'         => $this->formatUser($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/logout
    // ──────────────────────────────────────────────────────────────────────
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  GET /api/me
    // ──────────────────────────────────────────────────────────────────────
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role', 'shop']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Helper: format user JSON — không lộ matkhau
    // ──────────────────────────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        $user->loadMissing(['role', 'shop']);

        return [
            'ID_User'    => $user->ID_User,
            'HoTen'      => $user->HoTen,
            'email'      => $user->email,
            'diachi'     => $user->diachi,
            'sdt'        => $user->sdt,
            'TrangThai'  => $user->TrangThai,
            'ngaydangki' => $user->ngaydangki?->format('Y-m-d H:i:s'),
            'role'       => $user->role ? [
                'ID_role'  => $user->role->ID_role,
                'Ten_role' => $user->role->Ten_role,
            ] : null,
            'shop'       => $user->shop ? [
                'ID_Shop'        => $user->shop->ID_Shop,
                'TenShop'        => $user->shop->TenShop,
                'TrangThaiDuyet' => $user->shop->TrangThaiDuyet,
                'TrangThai'      => $user->shop->TrangThai,
            ] : null,
        ];
    }
}