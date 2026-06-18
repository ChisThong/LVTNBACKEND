<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class WalletService
{
    /**
     * Get or create a wallet for a user
     */
    public function getWallet(int $userId)
    {
        return Wallet::firstOrCreate(['user_id' => $userId]);
    }

    /**
     * Create a pending deposit transaction before calling payment gateway
     */
    public function createPendingTransaction(int $userId, float $amount, string $refType, string $refId)
    {
        return DB::transaction(function () use ($userId, $amount, $refType, $refId) {
            $wallet = $this->getWallet($userId);
            
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'status' => 'pending',
                'amount' => $amount,
                'balance_before' => null, // Will be set on confirm
                'balance_after' => null,  // Will be set on confirm
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);
        });
    }

    /**
     * Confirm a pending deposit transaction (Idempotent via DB lock)
     */
    public function confirmDeposit(string $refType, string $refId, float $amount)
    {
        return DB::transaction(function () use ($refType, $refId, $amount) {
            // Lock the transaction to prevent race conditions from duplicate IPNs
            $transaction = WalletTransaction::where('reference_type', $refType)
                ->where('reference_id', $refId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                throw new Exception("Không tìm thấy giao dịch chờ xử lý");
            }

            if ($transaction->status === 'success') {
                // Idempotent: Already processed successfully
                return $transaction;
            }

            if ($transaction->status === 'failed') {
                throw new Exception("Giao dịch này đã bị đánh dấu thất bại trước đó");
            }

            if ($transaction->amount != $amount) {
                throw new Exception("Số tiền giao dịch không khớp");
            }

            // Lock the wallet to prevent race conditions
            $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->firstOrFail();

            $before = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            $transaction->status = 'success';
            $transaction->balance_before = $before;
            $transaction->balance_after = $wallet->balance;
            $transaction->save();

            return $transaction;
        });
    }

    /**
     * Fail a pending deposit transaction
     */
    public function failTransaction(string $refType, string $refId)
    {
        return DB::transaction(function () use ($refType, $refId) {
            $transaction = WalletTransaction::where('reference_type', $refType)
                ->where('reference_id', $refId)
                ->lockForUpdate()
                ->first();

            if ($transaction && $transaction->status === 'pending') {
                $transaction->status = 'failed';
                $transaction->save();
            }

            return $transaction;
        });
    }

    /**
     * Make a payment (move balance to frozen balance)
     */
    public function payment(int $userId, float $amount, string $refType = null, string $refId = null)
    {
        return DB::transaction(function () use ($userId, $amount, $refType, $refId) {
            if ($refType && $refId) {
                $exists = WalletTransaction::where('reference_type', $refType)
                    ->where('reference_id', $refId)
                    ->where('type', 'payment')
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    throw new Exception('Giao dịch thanh toán này đã được xử lý');
                }
            }

            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $amount) {
                throw new Exception('Số dư khả dụng không đủ');
            }

            $before = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->frozen_balance += $amount;
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'payment',
                'status' => 'success',
                'amount' => -$amount,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);
        });
    }

    /**
     * Complete order and release funds to seller
     */
    public function completePurchase(int $buyerId, int $sellerId, float $amount, string $refType = null, string $refId = null)
    {
        return DB::transaction(function () use ($buyerId, $sellerId, $amount, $refType, $refId) {
            if ($refType && $refId) {
                $exists = WalletTransaction::where('reference_type', $refType)
                    ->where('reference_id', $refId)
                    ->where('type', 'release') // Release for buyer indicates completion started
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    throw new Exception('Giao dịch hoàn tất đơn hàng này đã được xử lý');
                }
            }

            // 1. Release frozen balance from buyer safely
            $buyerWallet = Wallet::where('user_id', $buyerId)->lockForUpdate()->firstOrFail();
            if ($buyerWallet->frozen_balance < $amount) {
                throw new Exception('Số dư đóng băng không đủ để hoàn tất đơn');
            }
            $buyerWallet->frozen_balance -= $amount;
            $buyerWallet->save();

            WalletTransaction::create([
                'wallet_id' => $buyerWallet->id,
                'type' => 'release',
                'status' => 'success',
                'amount' => 0, // No change to available balance
                'balance_before' => $buyerWallet->balance,
                'balance_after' => $buyerWallet->balance,
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);

            // 2. Add full funds to seller safely, then deduct commission
            $sellerWallet = Wallet::where('user_id', $sellerId)->lockForUpdate()->firstOrFail();
            
            $commission = $amount * 0.05; // 5% fee

            // Deposit 100%
            $beforeSeller = $sellerWallet->balance;
            $sellerWallet->balance += $amount;
            $sellerWallet->save();

            WalletTransaction::create([
                'wallet_id' => $sellerWallet->id,
                'type' => 'deposit',
                'status' => 'success',
                'amount' => $amount,
                'balance_before' => $beforeSeller,
                'balance_after' => $sellerWallet->balance,
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);

            // Deduct 5% commission
            $beforeCommission = $sellerWallet->balance;
            $sellerWallet->balance -= $commission;
            $sellerWallet->save();

            WalletTransaction::create([
                'wallet_id' => $sellerWallet->id,
                'type' => 'commission',
                'status' => 'success',
                'amount' => -$commission, 
                'balance_before' => $beforeCommission,
                'balance_after' => $sellerWallet->balance, 
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);

            return true;
        });
    }

    /**
     * Request withdrawal
     */
    public function withdraw(int $userId, float $amount, string $bankName, string $bankAccount)
    {
        return DB::transaction(function () use ($userId, $amount, $bankName, $bankAccount) {
            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();

            if ($wallet->balance < $amount) {
                throw new Exception('Số dư không đủ để rút');
            }

            $before = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->frozen_balance += $amount;
            $wallet->save();

            $withdrawal = \App\Models\Withdrawal::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'status' => 'pending',
                'bank_name' => $bankName,
                'bank_account' => $bankAccount
            ]);

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'withdraw',
                'status' => 'success', // The *request* was successfully deducted from balance
                'amount' => -$amount,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id
            ]);

            return $withdrawal;
        });
    }

    /**
     * Approve or reject withdrawal
     */
    public function processWithdrawal(int $withdrawalId, string $status, int $adminId)
    {
        return DB::transaction(function () use ($withdrawalId, $status, $adminId) {
            $withdrawal = \App\Models\Withdrawal::lockForUpdate()->findOrFail($withdrawalId);

            if ($withdrawal->status !== 'pending') {
                throw new Exception('Yêu cầu rút tiền này đã được xử lý');
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();

            if ($status === 'approved') {
                $withdrawal->status = 'approved';
                $withdrawal->admin_id = $adminId;
                $wallet->frozen_balance -= $withdrawal->amount;
                $wallet->save();
            } elseif ($status === 'rejected') {
                $withdrawal->status = 'rejected';
                $withdrawal->admin_id = $adminId;
                
                // Refund back to available balance
                $wallet->frozen_balance -= $withdrawal->amount;
                $before = $wallet->balance;
                $wallet->balance += $withdrawal->amount;
                $wallet->save();
                
                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'deposit', // Refund
                    'status' => 'success',
                    'amount' => $withdrawal->amount,
                    'balance_before' => $before,
                    'balance_after' => $wallet->balance,
                    'reference_type' => 'withdrawal_refund',
                    'reference_id' => $withdrawal->id
                ]);
            }

            $withdrawal->save();
            return $withdrawal;
        });
    }
}
