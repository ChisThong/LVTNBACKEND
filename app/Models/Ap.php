<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ap extends Model
{
    protected $table = 'ap';
    protected $primaryKey = 'ID_Ap';
    public $timestamps = false;
    protected $fillable = [
        'Ten_ap',
        'ID_Xa'
        ];
}
