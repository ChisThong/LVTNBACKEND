<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiTietDonHang extends Model
{
    use HasFactory;

    // 1. Khai báo chính xác tên bảng trong Database
    protected $table = 'chitietdonhang';

    // 2. Khai báo chính xác tên Khóa chính
    protected $primaryKey = 'ID_ChiTiet';

    // 3. Tắt chế độ tự động quản lý timestamp mặc định của Laravel
    public $timestamps = false;

    // 4. Cập nhật fillable theo đúng tên cột trong file SQL gốc của bạn
    protected $fillable = [
        'ID_DonHang',
        'ID_SanPham',
        'SoLuong',
        'TongGia', // Đổi từ DonGia sang TongGia để khớp với file SQL
    ];

    // 5. Cấu hình ép kiểu dữ liệu trả về (Casts)
    protected function casts(): array
    {
        return [
            'ID_DonHang' => 'integer',
            'ID_SanPham' => 'integer',
            'SoLuong'    => 'integer',
            'TongGia'    => 'decimal:2', // Khớp tên cột TongGia
        ];
    }

    // =========================================================================
    // ĐỊNH NGHĨA CÁC MỐI QUAN HỆ (RELATIONSHIPS)
    // =========================================================================

    /**
     * Mối quan hệ: Một dòng chi tiết đơn hàng bắt buộc thuộc về Một Đơn hàng con.
     */
    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ID_DonHang', 'ID_DonHang');
    }

    /**
     * Mối quan hệ: Một dòng chi tiết đơn hàng sẽ liên kết với Một Sản phẩm.
     * Sửa từ Product sang SanPham cho đúng tên Model hệ thống của bạn.
     */
    public function sanPham()
    {
        return $this->belongsTo(Product::class, 'ID_SanPham', 'ID_SanPham');
    }

    /**
     * Mối quan hệ: Một dòng chi tiết đơn hàng có thể có Một đánh giá.
     */
    public function danhGia()
    {
        return $this->hasOne(DanhGia::class, 'ID_ChiTiet', 'ID_ChiTiet');
    }
}