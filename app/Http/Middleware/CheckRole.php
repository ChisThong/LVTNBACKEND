<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Kiểm tra user có role được phép hay không.
     *
     * Cách dùng trong route:
     *   ->middleware('role:Admin')
     *   ->middleware('role:Admin,NguoiBan')
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa đăng nhập.',
            ], 401);
        }

        // Load relation nếu chưa có
        $user->loadMissing('role');

        if (! $user->role || ! in_array($user->role->Ten_role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                'roles_required' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
