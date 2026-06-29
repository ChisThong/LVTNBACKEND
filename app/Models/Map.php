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

    public function tinhThanh()
    {
        return $this->belongsTo(TinhThanh::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }

    public function xa()
    {
        return $this->belongsTo(Xa::class, 'ID_Xa', 'ID_Xa');
    }

    public function ap()
    {
        return $this->belongsTo(Ap::class, 'ID_Ap', 'ID_Ap');
    }
}
