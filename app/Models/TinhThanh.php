<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinhThanh extends Model
{

    protected $table = 'tinhthanh';

    protected $primaryKey = 'ID_TinhThanh';

    public $timestamps = false;

    protected $fillable = [
        'TenTinhThanh',
        'HinhAnh',
        'MoTa',
        'Tieude'
    ];



    public function products()
    {
        return $this->hasMany(Product::class, 'ID_TinhThanh', 'ID_TinhThanh');
    } 
    public function blogs()
    {
        return $this->hasMany(Blog::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }
    public function chiTietDonHang()
    {
        return $this->hasManyThrough(
            ChiTietDonHang::class, // Model đích muốn lấy dữ liệu (chitietdonhang)
            Product::class,        // Model trung gian (sanpham)
            'ID_TinhThanh',        // Khóa ngoại trên bảng trung gian (sanpham)
            'ID_SanPham',          // Khóa ngoại trên bảng đích (chitietdonhang)
            'ID_TinhThanh',        // Khóa chính của bảng tinhthanh
            'ID_SanPham'           // Khóa chính của bảng trung gian (sanpham)
        );
    }
}