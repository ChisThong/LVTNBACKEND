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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ──────────────────────────────────────────────────────────────────────
    //  Hằng số trạng thái — đồng bộ với NguoiDungController
    // ──────────────────────────────────────────────────────────────────────
    private const STATUS_INACTIVE = 0; // Chưa xác thực OTP
    private const STATUS_ACTIVE   = 1; // Hoạt động bình thường
    private const STATUS_BANNED   = 2; // Bị khóa bởi Admin
    private const ROLE_CUSTOMER   = 2; // NguoiMua (role mặc định khi đăng nhập Google)

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
            'email'      => $user->email,
            'otp_code'   => $otp,
            'created_at' => now(),
            'updated_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'is_used'    => false,
        ]);

        Mail::to($user->email)->send(new SendOtpMail($user->HoTen, $otp));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Helper: trả về response tài khoản bị khóa (403)
    // ──────────────────────────────────────────────────────────────────────
    private function responseBanned(): JsonResponse
    {
        return response()->json([
            'success'    => false,
            'error_code' => 'ACCOUNT_BANNED',
            'message'    => 'Tài khoản của bạn đã bị khóa do vi phạm chính sách của sàn. Vui lòng liên hệ Admin để được hỗ trợ!',
        ], 403);
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
            'TrangThai'  => self::STATUS_INACTIVE,
            'ngaydangki' => now(),
            'ID_role'    => $request->ID_role,
        ]);

        $activityData = [
            'id_target'  => $user->ID_User,
            'tieude'     => "Người dùng mới " . $user->HoTen . " vừa đăng ký tài khoản",
            'thoigian'   => now()->toDateTimeString(),
            'trangthai'  => 'Chờ duyệt',
            'type'       => 'shop',
        ];

        event(new \App\Events\AdminActivityEvent($activityData));
        $this->generateAndSendOtp($user);

        return response()->json([
            'success'    => true,
            'message'    => 'Đăng ký thành công. Vui lòng kiểm tra email để lấy mã OTP xác thực.',
            'data'       => ['email' => $user->email],
            'activities' => $activityData,
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

        if ($record->expires_at->isBefore(now())) {
            return response()->json([
                'success' => false,
                'message' => 'Mã OTP đã hết hạn. Vui lòng yêu cầu mã mới.',
            ], 422);
        }

        $record->update(['is_used' => true]);

        $user = User::where('email', $request->email)->first();
        $user->update(['TrangThai' => self::STATUS_ACTIVE]);

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

        if ((int) $user->TrangThai === self::STATUS_ACTIVE) {
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
     * Đăng nhập — kiểm tra email + mật khẩu.
     * Thứ tự: sai credentials → chưa xác thực email → bị khóa → OK.
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

        // ── Chưa xác thực email ────────────────────────────────────────────
        if ((int) $user->TrangThai === self::STATUS_INACTIVE) {
            return response()->json([
                'success'    => false,
                'error_code' => 'EMAIL_UNVERIFIED',
                'message'    => 'Tài khoản chưa xác thực email. Vui lòng kiểm tra hộp thư và nhập mã OTP.',
                'data'       => ['email' => $user->email],
            ], 403);
        }

        // ── Tài khoản bị khóa ──────────────────────────────────────────────
        if ((int) $user->TrangThai === self::STATUS_BANNED) {
            return $this->responseBanned();
        }

        // ── Đăng nhập thành công ───────────────────────────────────────────
        $user->tokens()->delete(); // single-session

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
    //  POST /api/auth/google
    // ──────────────────────────────────────────────────────────────────────
    /**
     * Đăng nhập / Đăng ký bằng Google OAuth2.
     */
    public function loginWithGoogle(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ], [
            'credential.required' => 'Google credential không hợp lệ.',
        ]);

        $credential = $request->input('credential');
        $isIdToken = (substr_count($credential, '.') === 2);

        if ($isIdToken) {
            try {
                $googleResponse = Http::timeout(10)
                    ->get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $credential);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể kết nối tới máy chủ Google. Vui lòng thử lại.',
                ], 503);
            }

            if ($googleResponse->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google token không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại.',
                ], 401);
            }

            $googleData = $googleResponse->json();

            $expectedClientId = config('services.google.client_id');
            if ($expectedClientId && isset($googleData['aud']) && $googleData['aud'] !== $expectedClientId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google token không thuộc ứng dụng này.',
                ], 401);
            }

        } else {
            try {
                $googleResponse = Http::timeout(10)
                    ->withHeaders(['Authorization' => 'Bearer ' . $credential])
                    ->get('https://www.googleapis.com/oauth2/v3/userinfo');
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không thể kết nối tới máy chủ Google. Vui lòng thử lại.',
                ], 503);
            }

            if ($googleResponse->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google access token không hợp lệ hoặc đã hết hạn. Vui lòng đăng nhập lại.',
                ], 401);
            }

            $googleData = $googleResponse->json();
        }

        // ── Trích xuất thông tin người dùng ────────────────────────────────
        $googleId = $googleData['sub']     ?? null;
        $email    = $googleData['email']   ?? null;
        $name     = $googleData['name']    ?? ($googleData['given_name'] ?? 'Người dùng Google');
        $avatar   = $googleData['picture'] ?? null;

        if (! $email || ! $googleId) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy thông tin từ tài khoản Google. Vui lòng thử lại.',
            ], 422);
        }

        // ── Tìm hoặc tạo User ───────────────────────────────────────────────
        $user       = User::where('email', $email)->first();
        $isNewUser  = false; // Flag phân biệt user mới đăng ký hay user đã tồn tại

        if ($user) {
            // ── User đã tồn tại ────────────────────────────────────────────────
            if ((int) $user->TrangThai === self::STATUS_BANNED) {
                return $this->responseBanned();
            }

            $updates = [];
            if (! $user->google_id)          $updates['google_id'] = $googleId;
            if (! $user->avatar && $avatar)  $updates['avatar']    = $avatar;
            if ($updates) $user->update($updates);

            if ((int) $user->TrangThai === self::STATUS_INACTIVE) {
                $user->update(['TrangThai' => self::STATUS_ACTIVE]);
            }

        } else {
            // ── User chưa tồn tại: tạo mới ─────────────────────────────────────
            $isNewUser = true;

            $user = User::create([
                'HoTen'      => $name,
                'email'      => $email,
                'matkhau'    => Hash::make(Str::random(32)),
                'TrangThai'  => self::STATUS_ACTIVE,
                'ngaydangki' => now(),
                'ID_role'    => self::ROLE_CUSTOMER,
                'google_id'  => $googleId,
                'avatar'     => $avatar,
            ]);

            try {
                event(new \App\Events\AdminActivityEvent([
                    'id_target'  => $user->ID_User,
                    'tieude'     => "Người dùng mới " . $user->HoTen . " vừa đăng ký bằng Google",
                    'thoigian'   => now()->toDateTimeString(),
                    'trangthai'  => 'Mới',
                    'type'       => 'user',
                ]));
            } catch (\Exception $e) {
                // Bỏ qua lỗi Pusher
            }
        }

        // ── Tạo Sanctum token và trả về ────────────────────────────────────
        $user->tokens()->delete();

        $token = $user->createToken('google_auth_token')->plainTextToken;

        return response()->json([
            'success'      => true,
            'is_new_user'  => $isNewUser,  // true = vừa đăng ký mới | false = đã có tài khoản
            'message'      => $isNewUser
                ? 'Đăng ký và đăng nhập bằng Google thành công! Chào mừng thành viên mới.'
                : 'Đăng nhập bằng Google thành công.',
            'data'         => [
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
        $user = $request->user()->load(['role', 'shop', 'wallet']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/forgot-password
    // ──────────────────────────────────────────────────────────────────────
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email:rfc,dns|exists:user,email',
        ], [
            'email.exists' => 'Email không tồn tại trong hệ thống.',
        ]);

        $user = User::where('email', $request->email)->first();
        $this->generateAndSendOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi mã OTP. Vui lòng kiểm tra email của bạn.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/reset-password
    // ──────────────────────────────────────────────────────────────────────
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email|exists:user,email',
            'otp_code' => 'required|string',
            'matkhau'  => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'matkhau.min'       => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'matkhau.confirmed' => 'Xác nhận mật khẩu không khớp.',
        ]);

        $record = EmailVerification::where('email', $request->email)
            ->where('otp_code', $request->otp_code)
            ->where('is_used', false)
            ->latest()
            ->first();

        if (! $record || $record->expires_at->isBefore(now())) {
            return response()->json([
                'success' => false,
                'message' => 'Mã OTP không đúng hoặc đã hết hạn.',
            ], 422);
        }

        $record->update(['is_used' => true]);

        $user = User::where('email', $request->email)->first();
        $user->update([
            'matkhau'   => Hash::make($request->matkhau),
            'TrangThai' => self::STATUS_ACTIVE,
        ]);

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công. Bạn có thể đăng nhập ngay bây giờ.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  POST /api/auth/change-password (Requires Auth)
    // ──────────────────────────────────────────────────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'old_password' => 'required|string',
            'matkhau'      => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'matkhau.min'       => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'matkhau.confirmed' => 'Xác nhận mật khẩu mới không khớp.',
        ]);

        $user = $request->user();

        // Kiểm tra mật khẩu cũ có đúng không
        if (! Hash::check($request->old_password, $user->matkhau)) {
            // Nếu user có google_id → khả năng là thuần Google (không biết mật khẩu)
            // → Trả message hướng dẫn rõ ràng hơn
            if ($user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản liên kết Google không thể đổi mật khẩu theo cách này. '
                               . 'Vui lòng dùng chức năng "Quên mật khẩu" để đặt mật khẩu mới.',
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu cũ không chính xác.',
            ], 400);
        }

        $user->update(['matkhau' => Hash::make($request->matkhau)]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công. Vui lòng đăng nhập lại.',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  PUT /api/auth/update-profile (Requires Auth)
    // ──────────────────────────────────────────────────────────────────────
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'HoTen'  => 'required|string|min:3|max:100',
            'sdt'    => 'required|regex:/^[0-9]{10}$/|unique:user,sdt,' . $user->ID_User . ',ID_User',
            'diachi' => 'nullable|string|max:255',
        ], [
            'sdt.regex'  => 'Số điện thoại phải gồm đúng 10 chữ số.',
            'sdt.unique' => 'Số điện thoại này đã được sử dụng.',
        ]);

        $user->update($request->only(['HoTen', 'sdt', 'diachi']));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật thông tin thành công.',
            'data'    => $this->formatUser($user),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Helper: format user JSON — không lộ matkhau
    // ──────────────────────────────────────────────────────────────────────
    private function formatUser(User $user): array
    {
        $user->loadMissing(['role', 'shop', 'wallet']);

        return [
            'ID_User'       => $user->ID_User,
            'HoTen'         => $user->HoTen,
            'email'         => $user->email,
            'diachi'        => $user->diachi,
            'sdt'           => $user->sdt,
            'TrangThai'     => $user->TrangThai,
            'avatar'          => $user->avatar,
            'has_google'      => (bool) $user->google_id,
            'is_social_login' => (bool) $user->google_id,  // true nếu đăng nhập bằng Google
            'ngaydangki'      => $user->ngaydangki?->format('Y-m-d H:i:s'),
            'role'          => $user->role ? [
                'ID_role'  => $user->role->ID_role,
                'Ten_role' => $user->role->Ten_role,
            ] : null,
            'shop'          => $user->shop ? [
                'ID_Shop'        => $user->shop->ID_Shop,
                'TenShop'        => $user->shop->TenShop,
                'TrangThaiDuyet' => $user->shop->TrangThaiDuyet,
                'TrangThai'      => $user->shop->TrangThai,
                'TenNganHang'    => $user->shop->TenNganHang,
                'SoTaiKhoang'    => $user->shop->SoTaiKhoang,
            ] : null,
            'wallet'        => $user->wallet ? [
                'id'             => $user->wallet->id,
                'balance'        => $user->wallet->balance,
                'frozen_balance' => $user->wallet->frozen_balance,
            ] : null,
        ];
    }
}
