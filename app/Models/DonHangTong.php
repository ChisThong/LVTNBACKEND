<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonHangTong extends Model
{
    use HasFactory;

    // 1. Khai báo chính xác tên bảng trong Database
    protected $table = 'donhangtong';

    // 2. Khai báo chính xác tên Khóa chính
    protected $primaryKey = 'ID_DonHangTong';

    // 3. Tắt chế độ tự động quản lý timestamp mặc định của Laravel
    public $timestamps = false;

    // 4. Khai báo các cột được phép điền dữ liệu (Mass Assignment)
    protected $fillable = [
        'TongGiaTien',
        'PhuongThucThanhToan',
        'TrangThaiThanhToan', // Lưu số: 0, 1, 2
        'NguoiNhan',
        'SDTNhan',
        'DiaChiNhan',
        'ID_User',
        'Ngaydat'
    ];

    // 5. Cấu hình ép kiểu dữ liệu trả về (Casts)
    protected function casts(): array
    {
        return [
            'TongGiaTien'         => 'decimal:2',
            'TrangThaiThanhToan'  => 'integer', // Ép kiểu tinyint về số nguyên trong PHP
            'Ngaydat'             => 'datetime'
        ];
    }

    // ĐỊNH NGHĨA CÁC HẰNG SỐ TRẠNG THÁI THANH TOÁN (TINYINT)
    const THANH_TOAN_CHUA_THANH_TOAN = 0;
    const THANH_TOAN_DA_THANH_TOAN   = 1;
    const THANH_TOAN_DA_HOAN_TIEN    = 2;

    // =========================================================================
    // ĐỊNH NGHĨA CÁC MỐI QUAN HỆ (RELATIONSHIPS)
    // =========================================================================

    /**
     * Mối quan hệ: Một Đơn hàng tổng thì thuộc về Một Người mua (User)
     * Giúp bạn gọi: $donHangTong->user->HoTen
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    /**
     * Mối quan hệ: Một Đơn hàng tổng có thể chứa Nhiều Đơn hàng con (cho từng Shop)
     * Giúp bạn gọi: $donHangTong->donHangs
     */
    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'ID_DonHangTong', 'ID_DonHangTong');
    }

    /**
     * Mối quan hệ: Một Đơn hàng tổng có duy nhất Một thông tin Thanh toán
     * Giúp bạn gọi: $donHangTong->thanhToan->code_GiaoDich
     */
    public function thanhToan()
    {
        return $this->hasOne(ThanhToan::class, 'ID_DonHangTong', 'ID_DonHangTong');
    }
}
