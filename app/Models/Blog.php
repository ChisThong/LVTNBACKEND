<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TinhThanh;
class Blog extends Model
{
    protected $table = 'blog';
    protected $primaryKey = 'ID_Blog';
    public $timestamps = false;
    protected $fillable = [
        'tittel',
        'tomtat',
        'noidung',
        'hinhanh',
        'ngaydang',
        'ID_User',
        'ID_TinhThanh'
    ];

    public function tinhThanh()
    {
        return $this->belongsTo(TinhThanh::class, 'ID_TinhThanh', 'ID_TinhThanh');
    }
     public function user()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }
}
