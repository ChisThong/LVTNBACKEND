<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GioHang extends Model
{
    protected $table = 'giohang';
    protected $primaryKey = 'ID_GioHang';
    public $timestamps = false;

    protected $fillable = ['ID_User', 'ngay_tao'];

    protected function casts(): array
    {
        return [
            'ngay_tao' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    public function chiTiet()
    {
        return $this->hasMany(ChiTietGioHang::class, 'ID_GioHang', 'ID_GioHang');
    }
}
