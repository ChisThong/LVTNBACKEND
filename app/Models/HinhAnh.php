<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HinhAnh extends Model
{
    protected $table = 'hinhanh';

    protected $primaryKey = 'ID_HinhAnh';

    public $timestamps = false;

    protected $fillable = [
        'HinhAnh',
        'ID_SanPham',
    ];

    /**
     * Sản phẩm chứa hình ảnh này.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'ID_SanPham', 'ID_SanPham');
    }
}
