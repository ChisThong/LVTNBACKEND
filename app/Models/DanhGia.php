<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DanhGia extends Model
{
    use HasFactory;
    protected $table = 'danhgia';
    protected $primaryKey = 'ID_DanhGia';
    protected $fillable = [
        'ID_User',
        'ID_SanPham',
        'ID_ChiTiet',
        'XepLoai',
        'BinhLuan',
        'HinhAnh',
        'NgayDanhGia'
    ];

    // 4. Tắt chế độ tự động quản lý cặp cột created_at/updated_at mặc định của Laravel
    // Vì bảng của bạn đang dùng cột 'NgayDanhGia' thay thế
    public $timestamps = false;

    /**
     * -----------------------------------------------------------------
     * Thiết lập các mối quan hệ (Relationships)
     * -----------------------------------------------------------------
     */

    // Liên kết với bảng Sản Phẩm (Giúp API index tìm theo ID_Shop của sản phẩm)
    public function sanPham()
    {
        // Giả định bảng san_phams dùng khóa chính là ID_SanPham
        return $this->belongsTo(Product::class, 'ID_SanPham', 'ID_SanPham');
    }

    // Liên kết với bảng PhanHoi (Lấy câu trả lời của Shop nếu có)
    public function phanHoi()
    {
        // Một đánh giá thì có tối đa một phản hồi từ Shop
        return $this->hasOne(PhanHoiDanhGia::class, 'ID_DanhGia', 'ID_DanhGia');
    }

    // Liên kết với bảng NguoiDung/User (Lấy thông tin người đánh giá)
    public function user()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }
}