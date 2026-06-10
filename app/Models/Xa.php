<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Xa extends Model
{
    protected $table = 'xa';
    protected $primaryKey = 'ID_Xa';
    public $timestamps = false;
    protected $fillable = [
        'Ten_xa',
        'ID_TinhThanh'
        ];
}
