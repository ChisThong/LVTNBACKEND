<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanPham extends Model
{
    protected $table = 'sanpham';

    protected $primaryKey = 'ID_SanPham';

    public $timestamps = false;

    protected $fillable = [
        'TenSP',
        'MoTa',
        'Gia',
        'SoLuong',
        'HinhAnh',
        'TrangThai',
        'NgayTao',
        'ID_User',
    ];

    protected function casts(): array
    {
        return [
            'Gia'       => 'decimal:2',
            'SoLuong'   => 'integer',
            'TrangThai' => 'integer',
            'NgayTao'   => 'datetime',
        ];
    }

    /**
     * Người bán (NguoiBan) sở hữu sản phẩm này.
     */
    public function nguoiBan()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    /**
     * Chi tiết đơn hàng có sản phẩm này.
     */
    public function chiTietDonHang()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ID_SanPham', 'ID_SanPham');
    }

    /**
     * Kiểm tra còn đủ tồn kho không.
     */
    public function conDuTonKho(int $soLuong): bool
    {
        return $this->SoLuong >= $soLuong;
    }
}
