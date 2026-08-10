<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletController extends Controller
{
    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    public function index()
    {
        $user = Auth::user();
        $wallet = $this->walletService->getWallet($user->ID_User);

        return response()->json([
            'status' => 'success',
            'data' => $wallet
        ]);
    }

    public function transactions()
    {
        $user = Auth::user();
        $wallet = $this->walletService->getWallet($user->ID_User);
        $transactions = $wallet->transactions()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
            'bank_name' => 'required|string',
            'bank_account' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            $withdrawal = $this->walletService->withdraw(
                $user->ID_User,
                $request->amount,
                $request->bank_name,
                $request->bank_account
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Yêu cầu rút tiền đã được tạo',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /api/withdrawals
     * Lịch sử các yêu cầu rút tiền của user đang đăng nhập (buyer hoặc seller)
     */
    public function withdrawalHistory(Request $request)
    {
        $user = Auth::user();

        $query = Withdrawal::where('user_id', $user->ID_User)
            ->orderBy('created_at', 'desc');

        // Filter theo trạng thái nếu có
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->get()->map(function ($w) {
            return [
                'id'           => $w->id,
                'amount'       => (float) $w->amount,
                'status'       => $w->status,
                'bank_name'    => $w->bank_name,
                'bank_account' => $w->bank_account,
                'note'         => $w->note,
                'created_at'   => $w->created_at,
                'updated_at'   => $w->updated_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $withdrawals,
        ]);
    }
}

