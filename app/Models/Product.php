<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // ─── Trạng thái sản phẩm ─────────────────────────────────────────────────
    const TRANG_THAI_HIEN       = 1;  // Đang bán
    const TRANG_THAI_AN         = 0;  // Ngừng bán / Ẩn
    const TRANG_THAI_HET_HANG   = 0;  // Hết hàng (cùng giá trị = ẩn khỏi listing)

    // ─── Trạng thái duyệt sản phẩm ───────────────────────────────────────────
    const DUYET_CHO             = 'cho_duyet';
    const DUYET_DA              = 'da_duyet';
    const DUYET_TU_CHOI         = 'tu_choi';

    // ─── Trạng thái hiển thị (Admin kiểm soát) ────────────────────────────
    const HIEN_THI_HIEN         = 'hien';
    const HIEN_THI_AN           = 'an';

    // ─── Auto-logic khi lưu ──────────────────────────────────────────────────
    /**
     * Hook Eloquent: tự động cập nhật TrangThai khi SoLuongTon = 0.
     * Áp dụng cho cả create() và update().
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $product): void {
            // Nếu số lượng tồn kho về 0 → tự động set Ngừng bán
            if (isset($product->SoLuongTon) && (int) $product->SoLuongTon === 0) {
                $product->TrangThai = self::TRANG_THAI_HET_HANG;
            }
        });
    }

    /**
     * Tên bảng thực tế trong MySQL.
     */
    protected $table = 'sanpham';

    /**
     * Khóa chính tuỳ chỉnh.
     */
    protected $primaryKey = 'ID_SanPham';

    /**
     * Không dùng timestamps (created_at / updated_at).
     */
    public $timestamps = false;

    protected $fillable = [
        'TenSanPham',
        'Tittle',
        'MoTa',
        'NguonGoc',
        'Gia',
        'SoLuongTon',
        'TrangThai',
        'LyDoAn',
        'TrangThaiDuyet',
        'LyDoTuChoi',
        'NgayDuyet',
        'TrangThaiHienThi',   // Admin visibility
        'LyDoAdminAn',        // Lý do Admin ẩn
        'Donvi',
        'ID_Shop',
        'ID_PhanLoai',
        'ID_TinhThanh',
    ];

    protected function casts(): array
    {
        return [
            'Gia'        => 'decimal:2',
            'SoLuongTon' => 'integer',
            'TrangThai'  => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    /**
     * Shop bán sản phẩm này.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'ID_Shop', 'ID_Shop');
    }

    /**
     * Phân loại sản phẩm.
     */
    public function phanLoai()
    {
        return $this->belongsTo(PhanLoaiSP::class, 'ID_PhanLoai', 'ID_PhanLoai');
    }

    /**
     * Hình ảnh sản phẩm (1 sản phẩm có nhiều ảnh).
     */
    public function hinhAnh()
    {
        return $this->hasMany(HinhAnh::class, 'ID_SanPham', 'ID_SanPham');
    }

    /**
     * Tỉnh/Thành sản phẩm thuộc về.
     */
    public function tinhThanh()
    {
        return $this->belongsTo(TinhThanh::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Kiểm tra còn đủ tồn kho.
     */
    public function conDuTonKho(int $soLuong): bool
    {
        return $this->SoLuongTon >= $soLuong;
    }

    /**
     * Scope: sản phẩm công khai cho website (bộ 3 điều kiện).
     */
    public function scopePubliclyVisible($query)
    {
        return $query
            ->where('TrangThaiDuyet', self::DUYET_DA)
            ->where('TrangThaiHienThi', self::HIEN_THI_HIEN)
            ->where('TrangThai', self::TRANG_THAI_HIEN);
    }

    /**
     * Scope: chỉ lấy sản phẩm đang hoạt động (TrangThai = 1).
     */
    public function scopeActive($query)
    {
        return $query->where('TrangThai', 1);
    }
}
