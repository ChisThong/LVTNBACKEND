<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWalletController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * GET /api/admin/wallet/stats
     * Tổng quan tài chính toàn hệ thống
     */
    public function stats()
    {
        // Tổng số dư ví khách hàng (role = 2: NguoiMua)
        $totalCustomerBalance = Wallet::whereHas('user', function ($q) {
            $q->where('ID_role', 2);
        })->sum('balance');

        // Tổng số dư ví người bán (role = 3: NguoiBan)
        $totalSellerBalance = Wallet::whereHas('user', function ($q) {
            $q->where('ID_role', 3);
        })->sum('balance');

        // Tổng số tiền nạp vào hệ thống (qua VNPay)
        $totalDeposits = WalletTransaction::where('type', 'deposit')
            ->whereIn('status', ['success', 'completed'])
            ->sum(DB::raw('ABS(amount)'));

        // Tổng số tiền đã thanh toán (qua ví)
        $totalPayments = WalletTransaction::where('type', 'payment')
            ->whereIn('status', ['success', 'completed'])
            ->sum(DB::raw('ABS(amount)'));

        // Tổng doanh thu hoa hồng
        $totalCommissions = WalletTransaction::where('type', 'commission')
            ->whereIn('status', ['success', 'completed'])
            ->sum(DB::raw('ABS(amount)'));

        // Tổng tiền đã rút
        $totalWithdrawals = WalletTransaction::where('type', 'withdraw')
            ->whereIn('status', ['success', 'completed', 'approved'])
            ->sum(DB::raw('ABS(amount)'));

        // Số yêu cầu rút chờ duyệt
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();

        // Tổng số ví đang hoạt động
        $totalWallets = Wallet::count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_customer_balance' => (float) $totalCustomerBalance,
                'total_seller_balance'   => (float) $totalSellerBalance,
                'total_deposits'         => (float) $totalDeposits,
                'total_payments'         => (float) $totalPayments,
                'total_commissions'      => (float) $totalCommissions,
                'total_withdrawals'      => (float) $totalWithdrawals,
                'pending_withdrawals'    => (int) $pendingWithdrawals,
                'total_wallets'          => (int) $totalWallets,
            ]
        ]);
    }

    /**
     * GET /api/admin/wallet/transactions
     * Danh sách giao dịch toàn hệ thống
     */
    public function transactions(Request $request)
    {
        $query = WalletTransaction::with([
            'wallet.user:ID_User,HoTen,email,ID_role'
        ])->orderBy('created_at', 'desc');

        // Filter: loại giao dịch
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter: trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter: từ ngày
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        // Filter: đến ngày
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter: tìm kiếm theo user (tên hoặc email)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('wallet.user', function ($q) use ($search) {
                $q->where('HoTen', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter: theo role user
        if ($request->filled('role')) {
            $query->whereHas('wallet.user', function ($q) use ($request) {
                $q->where('ID_role', $request->role);
            });
        }

        $perPage = (int) ($request->per_page ?? 20);
        $transactions = $query->paginate($perPage);

        // Gắn thêm thông tin user vào từng transaction
        $transactions->getCollection()->transform(function ($txn) {
            $user = $txn->wallet?->user;
            return [
                'id'              => $txn->id,
                'type'            => $txn->type,
                'status'          => $txn->status,
                'amount'          => $txn->amount,
                'balance_before'  => $txn->balance_before,
                'balance_after'   => $txn->balance_after,
                'reference_type'  => $txn->reference_type,
                'reference_id'    => $txn->reference_id,
                'created_at'      => $txn->created_at,
                'user' => $user ? [
                    'ID_User' => $user->ID_User,
                    'HoTen'   => $user->HoTen,
                    'email'   => $user->email,
                    'ID_role' => $user->ID_role,
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $transactions,
        ]);
    }

    /**
     * GET /api/admin/wallet/withdrawals
     * Danh sách yêu cầu rút tiền
     */
    public function withdrawals(Request $request)
    {
        $query = Withdrawal::with('user:ID_User,HoTen,email,ID_role')
            ->orderBy('created_at', 'desc');

        // Filter: trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter: tìm kiếm user
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('HoTen', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter: ngày
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter: lọc theo role (2=NguoiMua, 3=NguoiBan)
        if ($request->filled('role')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('ID_role', $request->role);
            });
        }

        $roleMap = [1 => 'Admin', 2 => 'NguoiMua', 3 => 'NguoiBan'];

        $withdrawals = $query->get()->map(function ($w) use ($roleMap) {
            $user = $w->user;
            return [
                'id'           => $w->id,
                'amount'       => (float) $w->amount,
                'status'       => $w->status,
                'bank_name'    => $w->bank_name,
                'bank_account' => $w->bank_account,
                'note'         => $w->note,
                'created_at'   => $w->created_at,
                'updated_at'   => $w->updated_at,
                'user' => $user ? [
                    'ID_User'   => $user->ID_User,
                    'HoTen'     => $user->HoTen,
                    'email'     => $user->email,
                    'ID_role'   => $user->ID_role,
                    'role_name' => $roleMap[$user->ID_role] ?? 'Unknown',
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $withdrawals
        ]);
    }

    /**
     * PUT /api/admin/wallet/withdrawals/{id}
     * Duyệt hoặc từ chối yêu cầu rút tiền
     */
    public function processWithdrawal(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'note'   => 'nullable|string|max:500',
        ]);

        try {
            $adminId = \Illuminate\Support\Facades\Auth::id();
            $note    = $request->input('note');
            $withdrawal = $this->walletService->processWithdrawal($id, $request->status, $adminId, $note);

            return response()->json([
                'status'  => 'success',
                'message' => 'Đã cập nhật trạng thái rút tiền',
                'data'    => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
