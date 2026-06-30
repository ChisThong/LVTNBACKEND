<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TinNhan extends Model
{
    protected $table = 'tinnhanchat';
    protected $primaryKey = 'ID_TinNhan';
    public $timestamps = false;

    protected $fillable = ['ID_PhongChat', 'LoaiNguoiGui', 'ID_NguoiGui', 'NoiDung', 'DaDoc', 'ThoiGianGui'];
}
