<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinhThanh extends Model
{
    // 1. Khai báo các thuộc tính của Model nằm tập trung ở đây
    protected $table = 'tinhthanh';

    protected $primaryKey = 'ID_TinhThanh';

    public $timestamps = false;

    protected $fillable = [
        'TenTinhThanh',
        'HinhAnh',
        'MoTa',
        'Tieude'
    ];

    // 2. Các hàm liên kết dữ liệu (Relationships) nằm ở dưới này

    /**
     * Các sản phẩm thuộc tỉnh/thành này.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'ID_TinhThanh', 'ID_TinhThanh');
    } 
    /**
     * Các bài viết (blogs) thuộc tỉnh/thành này.
     */
    public function blogs()
    {
        return $this->hasMany(Blog::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }
}