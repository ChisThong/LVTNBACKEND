<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class WalletService
{

    public function getWallet(int $userId)
    {
        return Wallet::firstOrCreate(['user_id' => $userId]);
    }

 
    public function createPendingTransaction(int $userId, float $amount, string $refType, string $refId)
    {
        return DB::transaction(function () use ($userId, $amount, $refType, $refId) {
            $wallet = $this->getWallet($userId);
            
            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'deposit',
                'status' => 'pending',
                'amount' => $amount,
                'balance_before' => null, 
                'balance_after' => null, 
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);
        });
    }

    public function confirmDeposit(string $refType, string $refId, float $amount)
    {
        return DB::transaction(function () use ($refType, $refId, $amount) {
            $transaction = WalletTransaction::where('reference_type', $refType)
                ->where('reference_id', $refId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                throw new Exception("Không tìm thấy giao dịch chờ xử lý");
            }

            if ($transaction->status === 'success') {
                return $transaction;
            }

            if ($transaction->status === 'failed') {
                throw new Exception("Giao dịch này đã bị đánh dấu thất bại trước đó");
            }

            if ($transaction->amount != $amount) {
                throw new Exception("Số tiền giao dịch không khớp");
            }

            $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->firstOrFail();

            $before = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            $transaction->status = 'success';
            $transaction->balance_before = $before;
            $transaction->balance_after = $wallet->balance;
            $transaction->save();

            $this->settlePendingCommissions($wallet);

            return $transaction;
        });
    }

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

    public function completePurchase(int $buyerId, int $sellerId, float $amount, string $refType = null, string $refId = null)
    {
        return DB::transaction(function () use ($buyerId, $sellerId, $amount, $refType, $refId) {
            if ($refType && $refId) {
                $exists = WalletTransaction::where('reference_type', $refType)
                    ->where('reference_id', $refId)
                    ->where('type', 'release') 
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    throw new Exception('Giao dịch hoàn tất đơn hàng này đã được xử lý');
                }
            }

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
                'amount' => 0, 
                'balance_before' => $buyerWallet->balance,
                'balance_after' => $buyerWallet->balance,
                'reference_type' => $refType,
                'reference_id' => $refId
            ]);

            $sellerWallet = Wallet::where('user_id', $sellerId)->lockForUpdate()->firstOrFail();
            
            $commission = $amount * 0.05; 

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
                'status' => 'success', 
                'amount' => -$amount,
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'reference_type' => 'withdrawal',
                'reference_id' => $withdrawal->id
            ]);

            return $withdrawal;
        });
    }

    public function processWithdrawal(int $withdrawalId, string $status, int $adminId, ?string $note = null)
    {
        return DB::transaction(function () use ($withdrawalId, $status, $adminId, $note) {
            $withdrawal = \App\Models\Withdrawal::lockForUpdate()->findOrFail($withdrawalId);

            if ($withdrawal->status !== 'pending') {
                throw new Exception('Yêu cầu rút tiền này đã được xử lý');
            }

            $wallet = Wallet::where('id', $withdrawal->wallet_id)->lockForUpdate()->firstOrFail();

            if ($status === 'approved') {
                $withdrawal->status   = 'approved';
                $withdrawal->admin_id = $adminId;
                $withdrawal->note     = $note;
                $wallet->frozen_balance -= $withdrawal->amount;
                $wallet->save();
            } elseif ($status === 'rejected') {
                $withdrawal->status   = 'rejected';
                $withdrawal->admin_id = $adminId;
                $withdrawal->note     = $note;
  
                $wallet->frozen_balance -= $withdrawal->amount;
                $before = $wallet->balance;
                $wallet->balance += $withdrawal->amount;
                $wallet->save();
                
                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'type' => 'deposit',
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
    public function settlePendingCommissions(Wallet $wallet): void
    {

        $pendingDebts = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('type', 'commission')
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingDebts->isEmpty()) {
            return;
        }
        $adminUserId = 6; 
        foreach ($pendingDebts as $debt) {
            $owed = abs($debt->amount); 

            if ($wallet->balance < $owed) {
                break; 
            }

            $beforeSeller = $wallet->balance;
            $wallet->balance -= $owed;
            $wallet->save();

            $debt->status         = 'success';
            $debt->reference_type = 'cod_commission_settled';
            $debt->balance_before = $beforeSeller;
            $debt->balance_after  = $wallet->balance;
            $debt->save();

            $adminWallet = Wallet::lockForUpdate()->where('user_id', $adminUserId)->first();
            if ($adminWallet) {
                $beforeAdmin           = $adminWallet->balance;
                $adminWallet->balance += $owed;
                $adminWallet->save();

                WalletTransaction::create([
                    'wallet_id'      => $adminWallet->id,
                    'type'           => 'commission',
                    'status'         => 'success',
                    'amount'         => $owed,
                    'balance_before' => $beforeAdmin,
                    'balance_after'  => $adminWallet->balance,
                    'reference_type' => $debt->reference_type,
                    'reference_id'   => $debt->reference_id,
                ]);
            }
        }
    }
}
