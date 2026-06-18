<?php

namespace App\Http\Controllers;

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
}
