<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    protected $table = 'bando';
    protected $primaryKey = 'ID';
    public $timestamps = false;
    protected $fillable = [
        'PhanLoai',
        'TenDacSan',
        'MoTa',
        'ViDo',
        'KinhDo',
        'TrangThai',
        'NhanXet',
        'HinhAnh',
        'ID_TinhThanh',
        'ID_Xa',
        'ID_Ap',
        ];
}
