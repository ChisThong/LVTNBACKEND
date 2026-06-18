<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function stats()
    {
        $totalDeposits = WalletTransaction::where('type', 'deposit')
            ->where('reference_type', 'vnpay')
            ->sum('amount');

        $totalCommissions = WalletTransaction::where('type', 'commission')
            ->sum(\DB::raw('ABS(amount)')); // commissions are logged as negative

        $totalWithdrawals = WalletTransaction::where('type', 'withdraw')
            ->sum(\DB::raw('ABS(amount)')); // withdrawals are logged as negative

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_deposits' => $totalDeposits,
                'total_commissions' => $totalCommissions,
                'total_withdrawals' => $totalWithdrawals
            ]
        ]);
    }

    public function withdrawals()
    {
        $withdrawals = Withdrawal::with('user:ID_User,HoTen,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $withdrawals
        ]);
    }

    public function processWithdrawal(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected'
        ]);

        try {
            // Include admin ID for audit trail
            $adminId = \Illuminate\Support\Facades\Auth::id();
            $withdrawal = $this->walletService->processWithdrawal($id, $request->status, $adminId);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã cập nhật trạng thái rút tiền',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
