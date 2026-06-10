<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietDonHang extends Model
{
    protected $table = 'chitietdonhang';

    protected $primaryKey = 'ID_ChiTiet';

    public $timestamps = false;

    protected $fillable = [
        'ID_DonHang',
        'ID_SanPham',
        'SoLuong',
        'DonGia',
    ];

    protected function casts(): array
    {
        return [
            'SoLuong' => 'integer',
            'DonGia'  => 'decimal:2',
        ];
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ID_DonHang', 'ID_DonHang');
    }

    public function sanPham()
    {
        return $this->belongsTo(Product::class, 'ID_SanPham', 'ID_SanPham');
    }
}
