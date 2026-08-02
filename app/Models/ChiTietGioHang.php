<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietGioHang extends Model
{
    protected $table = 'chitietgiohang';
    protected $primaryKey = 'ID_ChiTietGioHang';
    public $timestamps = false;

    protected $fillable = ['ID_GioHang', 'ID_SanPham', 'SoLuong', 'GiaSP'];

    protected function casts(): array
    {
        return [
            'SoLuong' => 'integer',
            'GiaSP'   => 'decimal:2',
        ];
    }

    public function gioHang()
    {
        return $this->belongsTo(GioHang::class, 'ID_GioHang', 'ID_GioHang');
    }

    public function sanPham()
    {
        return $this->belongsTo(Product::class, 'ID_SanPham', 'ID_SanPham');
    }
}
