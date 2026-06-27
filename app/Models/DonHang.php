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

    // Cập nhật fillable theo các cột TINYINT và cấu trúc 3 bảng mới
    protected $fillable = [
        'ID_DonHangTong', // Khóa ngoại liên kết với bảng đơn hàng tổng
        'MaDonHangCon',   // Mã đơn hàng con (DHC_...)
        'ID_Shop',        // Đơn con này thuộc về shop nào
        'ID_User',        // Ai là người mua
        'TongGia',        // Tiền hàng riêng của shop này (Khớp file SQL: TongGia)
        'PhiVanChuyen',   // Phí ship riêng của shop này
        'TrangThai',      // Lưu số: 0, 1, 2, 3 (TINYINT)
        'MaVanDon',       // Mã vận đơn giả lập để giao hàng
        'date',           // Ngày đặt đơn (Khớp file SQL: date)
    ];

    protected function casts(): array
    {
        return [
            'ID_DonHangTong' => 'integer',
            'ID_Shop'        => 'integer',
            'ID_User'        => 'integer',
            'TongGia'        => 'decimal:2',
            'PhiVanChuyen'   => 'decimal:2',
            'TrangThai'      => 'integer', // Chuẩn hóa tinyint về dạng int trong PHP
            'date'           => 'datetime',
        ];
    }

    // ĐỊNH NGHĨA TRẠNG THÁI ĐƠN HÀNG DẠNG SỐ (TINYINT)
    const TRANG_THAI_CHO_XAC_NHAN = 0;
    const TRANG_THAI_DA_XAC_NHAn=1;
    const TRANG_THAI_DANG_GIAO = 2;
    const TRANG_THAI_HOAN_TAT  = 3;
    const TRANG_THAI_HUY       = 4;

    // =========================================================================
    // ĐỊNH NGHĨA CÁC MỐI QUAN HỆ (RELATIONSHIPS)
    // =========================================================================

    /**
     * Mối quan hệ: Một đơn hàng con phải thuộc về Một Đơn hàng tổng.
     */
    public function donHangTong()
    {
        return $this->belongsTo(DonHangTong::class, 'ID_DonHangTong', 'ID_DonHangTong');
    }

    /**
     * Mối quan hệ: Một đơn hàng con thuộc về Một Shop duy nhất.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'ID_Shop', 'ID_Shop');
    }

    /**
     * Mối quan hệ: Người mua đặt đơn hàng này.
     */
    public function nguoiMua()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    /**
     * Chi tiết các sản phẩm nằm trong đơn hàng con này.
     */
    public function chiTiet()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ID_DonHang', 'ID_DonHang');
    }
    public function scopeBetweenDate($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}