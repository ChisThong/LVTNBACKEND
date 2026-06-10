<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    // ─── Trạng thái duyệt ────────────────────────────────────────────────────
    const DUYET_CHO     = 'cho_duyet';
    const DUYET_DA      = 'da_duyet';
    const DUYET_TU_CHOI = 'tu_choi';

    protected $table = 'shop';

    protected $primaryKey = 'ID_Shop';

    public $timestamps = false;

    protected $fillable = [
        'TenShop',
        'logo',
        'baner',
        'SCCD',
        'DiaChi',
        'TenNganHang',
        'SoTaiKhoang',
        'Tittle',
        'GioiThieu',
        'NgayDangKy',
        'NgayDuyet',
        'TrangThaiDuyet',
        'TrangThai',
        'ID_User',
    ];

    protected function casts(): array
    {
        return [
            'TrangThai'  => 'integer',
            'NgayDangKy' => 'datetime',
            'NgayDuyet'  => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * User (NguoiBan) sở hữu shop này.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    /**
     * Các sản phẩm thuộc shop này.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'ID_Shop', 'ID_Shop');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isDaDuyet(): bool
    {
        return $this->TrangThaiDuyet === self::DUYET_DA;
    }

    public function isChoDuyet(): bool
    {
        return $this->TrangThaiDuyet === self::DUYET_CHO;
    }
}
