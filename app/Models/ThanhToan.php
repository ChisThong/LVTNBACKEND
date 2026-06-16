<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThanhToan extends Model
{
    use HasFactory;

    // 1. Khai báo chính xác tên bảng trong Database
    protected $table = 'thanhtoan';

    // 2. Khai báo chính xác tên Khóa chính (thay vì 'id')
    protected $primaryKey = 'ID_ThanhToan';

    // 3. Nếu bảng dùng 'Date' thay vì 'created_at' và 'updated_at' của Laravel,
    // ta tắt chế độ tự động điền timestamp để tránh lỗi SQL
    public $timestamps = false;

    // 4. Các cột được phép tương tác dữ liệu (Mass Assignment)
    protected $fillable = [
        'PhuongThuc',
        'code_GiaoDich',
        'MoMo_TransId',          // Lưu mã giao dịch MoMo để sau này làm Refund
        'VNPay_TransactionNo',   // Lưu mã giao dịch VNPay để sau này làm Refund
        'SoTien',
        'TrangThai',
        'Date',
        'ID_DonHangTong'         // Khóa ngoại liên kết với đơn hàng tổng mới
    ];

    // =========================================================================
    // ĐỊNH NGHĨA CÁC MỐI QUAN HỆ (RELATIONSHIPS)
    // =========================================================================

    /**
     * Mối quan hệ: Một bản ghi thanh toán sẽ thuộc về Một Đơn hàng tổng
     * Giúp bạn gọi từ Controller: $thanhToan->donHangTong->NguoiNhan
     */
    public function donHangTong()
    {
        return $this->belongsTo(DonHangTong::class, 'ID_DonHangTong', 'ID_DonHangTong');
    }
}