<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Đăng ký tài khoản mới.
     *
     * POST /api/auth/register
     *
     * Body:
     *   HoTen            (required, min:3)
     *   email            (required, unique)
     *   matkhau          (required, min:6)
     *   matkhau_confirmation (required)
     *   diachi           (optional)
     *   sdt              (optional, 10 chữ số, unique)
     *   ID_role          (optional): 2=NguoiMua | 3=NguoiBan — mặc định 2
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        // ID_role đã được gán mặc định = 2 (NguoiMua) trong passedValidation() của RegisterRequest
        $user = User::create([
            'HoTen'      => $request->HoTen,
            'email'      => $request->email,
            'matkhau'    => Hash::make($request->matkhau),   // Hash thủ công → cột matkhau
            'diachi'     => $request->diachi,
            'sdt'        => $request->sdt,
            'TrangThai'  => 1,
            'ngaydangki' => now(),
            'ID_role'    => $request->ID_role,               // 2=NguoiMua, 3=NguoiBan
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công.',
            'data'    => [
                'user'         => $this->formatUser($user),
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Đăng nhập — kiểm tra email + mật khẩu bằng cột matkhau.
     *
     * POST /api/auth/login
     * Body: email, matkhau
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Tìm user theo email trong bảng `user`
        $user = User::where('email', $request->email)->first();

        // Kiểm tra tồn tại và Hash::check với cột matkhau
        if (! $user || ! Hash::check($request->matkhau, $user->matkhau)) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        // Kiểm tra tài khoản có bị khoá không
        if ((int) $user->TrangThai === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khoá. Vui lòng liên hệ quản trị viên.',
            ], 403);
        }

        // Xoá tất cả token cũ (single-session per user)
        $user->tokens()->delete();

        // Tạo Sanctum token mới
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

    /**
     * Đăng xuất — thu hồi token hiện tại.
     *
     * POST /api/auth/logout
     * Header: Authorization: Bearer <token>
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    /**
     * Thông tin user đang đăng nhập kèm role.
     *
     * GET /api/me
     * Header: Authorization: Bearer <token>
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role');

        return response()->json([
            'success' => true,
            'data'    => $this->formatUser($user),
        ]);
    }

    /**
     * Format dữ liệu user trả về JSON — không lộ matkhau.
     */
    private function formatUser(User $user): array
    {
        $user->loadMissing('role');

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
        ];
    }
}
