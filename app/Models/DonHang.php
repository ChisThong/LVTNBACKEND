<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonHang extends Model
{
    use HasFactory;

    protected $table = 'donhang';

    protected $primaryKey = 'ID_DonHang';

    public $timestamps = false;

  
    protected $fillable = [
        'ID_DonHangTong',
        'MaDonHangCon',   
        'ID_Shop',        
        'ID_User',        
        'TongGia',        
        'PhiVanChuyen',   
        'TrangThai',      
        'MaVanDon',       
        'date',          
    ];

    protected function casts(): array
    {
        return [
            'ID_DonHangTong' => 'integer',
            'ID_Shop'        => 'integer',
            'ID_User'        => 'integer',
            'TongGia'        => 'decimal:2',
            'PhiVanChuyen'   => 'decimal:2',
            'TrangThai'      => 'integer', 
            'date'           => 'datetime',
        ];
    }

    const TRANG_THAI_CHO_XAC_NHAN = 0;
    const TRANG_THAI_DA_XAC_NHAn=1;
    const TRANG_THAI_DANG_GIAO = 2;
    const TRANG_THAI_HOAN_TAT  = 3;
    const TRANG_THAI_HUY       = 4;

    public function donHangTong()
    {
        return $this->belongsTo(DonHangTong::class, 'ID_DonHangTong', 'ID_DonHangTong');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'ID_Shop', 'ID_Shop');
    }

    public function nguoiMua()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    public function chiTiet()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ID_DonHang', 'ID_DonHang');
    }
    public function scopeBetweenDate($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}