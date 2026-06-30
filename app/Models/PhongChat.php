<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhongChat extends Model
{
    protected $table = 'phongchat';
    protected $primaryKey = 'ID_PhongChat';
    public $timestamps = false; 

    protected $fillable = ['ID_User', 'ID_Shop', 'TinNhanCuoi', 'ThoiGianCapNhat', 'ThoiGianTao'];
}
