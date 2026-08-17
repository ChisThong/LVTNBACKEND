<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'role';

    protected $primaryKey = 'ID_role';

    public $timestamps = false;

    protected $fillable = [
        'Ten_role',
    ];

    const ADMIN      = 1;
    const NGUOI_MUA  = 2;
    const NGUOI_BAN  = 3;

    public function users()
    {
        return $this->hasMany(User::class, 'ID_role', 'ID_role');
    }
}
