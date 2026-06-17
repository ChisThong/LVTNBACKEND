<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhanHoiDanhGia extends Model
{
    protected $table = 'PhanHoi';
    protected $primaryKey = 'ID_PhanHoi';
    public $timestamps = false;
    protected $fillable = ['ID_DanhGia', 'NoiDungPhanHoi', 'NgayPhanHoi'];
}
