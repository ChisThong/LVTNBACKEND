<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckAccountStatus – Middleware kiểm tra trạng thái tài khoản.
 *
 * Áp dụng sau middleware auth:sanctum (user đã được xác thực token).
 * Chặn tất cả request từ tài khoản có TrangThai = 2 (bị khóa).
 *
 * Cách đăng ký:
 *   bootstrap/app.php → alias 'check.status'
 *   routes/api.php    → áp dụng vào nhóm auth:sanctum
 */
class CheckAccountStatus
{
    /**
     * Hằng số trạng thái — đồng bộ với NguoiDungController::changeclock()
     */
    private const STATUS_BANNED = 2;

    /**
     * Xử lý request đến.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Nếu chưa có user (route public), bỏ qua
        if (! $user) {
            return $next($request);
        }

        // Kiểm tra tài khoản bị khóa
        if ((int) $user->TrangThai === self::STATUS_BANNED) {
            return response()->json([
                'success'    => false,
                'error_code' => 'ACCOUNT_BANNED',
                'message'    => 'Tài khoản của bạn đã bị khóa do vi phạm chính sách của sàn. Vui lòng liên hệ Admin để được hỗ trợ!',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
