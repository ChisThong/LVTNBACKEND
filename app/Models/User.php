<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'user';

   
    protected $primaryKey = 'ID_User';

    
    public $timestamps = false;

    /**
     * 
     *
     * @var list<string>
     */
    protected $fillable = [
        'HoTen',
        'email',
        'diachi',
        'sdt',
        'matkhau',
        'TrangThai',
        'ngaydangki',
        'ID_role',
        'google_id',   
        'avatar',      
    ];

    /**
     * 
     *
     * @var list<string>
     */
    protected $hidden = [
        'matkhau',
    ];

    
    protected $appends = ['has_password'];

    /**
     * 
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TrangThai'  => 'integer',
            'ngaydangki' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->matkhau;
    }

    public function getHasPasswordAttribute(): bool
    {
        return isset($this->attributes['matkhau'])
            && !empty($this->attributes['matkhau']);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'ID_role', 'ID_role');
    }

  
    public function shop()
    {
        return $this->hasOne(Shop::class, 'ID_User', 'ID_User');
    }

   
    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id', 'ID_User');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->Ten_role === $roleName;
    }

   
    public function hasRoleId(int $roleId): bool
    {
        return (int) $this->ID_role === $roleId;
    }

    public function scopeBetweenDate($query, $startDate, $endDate)
    {
        return $query->whereBetween('ngaydangki', [$startDate, $endDate]);
    }
}

