<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $table = 'shop';

    protected $primaryKey = 'ID_Shop';

    public $timestamps = false;

    protected $fillable = [
        'TenShop',
        'ID_User',
    ];

    /**
     * User (NguoiBan) sở hữu shop này.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'ID_User', 'ID_User');
    }

    /**
     * Các sản phẩm thuộc shop này.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'ID_Shop', 'ID_Shop');
    }
}
