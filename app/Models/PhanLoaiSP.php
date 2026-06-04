<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhanLoaiSP extends Model
{
    protected $table = 'phanloaisp';

    protected $primaryKey = 'ID_PhanLoai';

    public $timestamps = false;

    protected $fillable = [
        'TenLoai',
    ];

    /**
     * Các sản phẩm thuộc phân loại này.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'ID_PhanLoai', 'ID_PhanLoai');
    }
}
