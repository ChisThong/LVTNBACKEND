<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinhThanh extends Model
{
    protected $table = 'tinhthanh';

    protected $primaryKey = 'ID_TinhThanh';

    public $timestamps = false;

    protected $fillable = [
        'TenTinhThanh',
    ];

    /**
     * Các sản phẩm thuộc tỉnh/thành này.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }
}
