<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinhThanh extends Model
{
    protected $table = 'tinhthanh';
    protected $primaryKey = 'ID_TinhThanh';
    public $timestamps = false;
    protected $fillable = [
        'TenTinhThanh'
        ];

    public function blogs()
    {
        return $this->hasMany(Blog::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }
}
