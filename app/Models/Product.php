<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // ─── Trạng thái sản phẩm ─────────────────────────────────────────────────
    const TRANG_THAI_HIEN       = 1;  
    const TRANG_THAI_AN         = 0;  
    const TRANG_THAI_HET_HANG   = 0;  

    // ─── Trạng thái duyệt sản phẩm ───────────────────────────────────────────
    const DUYET_CHO             = 'cho_duyet';
    const DUYET_DA              = 'da_duyet';
    const DUYET_TU_CHOI         = 'tu_choi';

    // ─── Trạng thái hiển thị (Admin kiểm soát) ────────────────────────────
    const HIEN_THI_HIEN         = 'hien';
    const HIEN_THI_AN           = 'an';

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $product): void {
           
            if (isset($product->SoLuongTon) && (int) $product->SoLuongTon === 0) {
                $product->TrangThai = self::TRANG_THAI_HET_HANG;
            }
        });
    }

   
    protected $table = 'sanpham';

    protected $primaryKey = 'ID_SanPham';


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

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'ID_Shop', 'ID_Shop');
    }

 
    public function phanLoai()
    {
        return $this->belongsTo(PhanLoaiSP::class, 'ID_PhanLoai', 'ID_PhanLoai');
    }

   
    public function hinhAnh()
    {
        return $this->hasMany(HinhAnh::class, 'ID_SanPham', 'ID_SanPham');
    }


    public function tinhThanh()
    {
        return $this->belongsTo(TinhThanh::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function conDuTonKho(int $soLuong): bool
    {
        return $this->SoLuongTon >= $soLuong;
    }


    public function scopePubliclyVisible($query)
    {
        return $query
            ->where('TrangThaiDuyet', self::DUYET_DA)
            ->where('TrangThaiHienThi', self::HIEN_THI_HIEN)
            ->where('TrangThai', self::TRANG_THAI_HIEN);
    }

  
    public function scopeActive($query)
    {
        return $query->where('TrangThai', 1);
    }

  
    public function scopeBetweenDate($query, $startDate, $endDate)
    {
        return $query->whereBetween('NgayDuyet', [$startDate, $endDate]);
    }

 
    public function chiTietDonHang()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ID_SanPham', 'ID_SanPham');
    }
}


