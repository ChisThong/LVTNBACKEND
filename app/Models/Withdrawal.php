<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'admin_id',
        'amount',
        'status',
        'bank_name',
        'bank_account'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'ID_User');
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}
