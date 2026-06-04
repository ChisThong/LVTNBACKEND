<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    protected $table = 'donhang';

    protected $primaryKey = 'ID_DonHang';

    public $timestamps = false;

    protected $fillable = [
        'ID_User',
        'DiaChiGiao',
        'SDTNhanHang',
        'TongTien',
        'TrangThai',
        'NgayDat',
    ];

    protected function casts(): array
    {
        return [
            'TongTien'  => 'decimal:2',
            'TrangThai' => 'integer',
            'NgayDat'   => 'datetime',
        ];
    }

    // Trạng thái đơn hàng
    const TRANG_THAI_CHO_XAC_NHAN = 0;
    const TRANG_THAI_XAC_NHAN     = 1;
    const TRANG_THAI_DANG_GIAO    = 2;
    const TRANG_THAI_DA_GIAO      = 3;
    const TRANG_THAI_HUY          = 4;

    /**
     * Người mua đặt đơn hàng này.
     */
    public function nguoiMua()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    /**
     * Chi tiết các sản phẩm trong đơn hàng.
     */
    public function chiTiet()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ID_DonHang', 'ID_DonHang');
    }
}
