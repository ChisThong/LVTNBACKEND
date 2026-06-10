<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * Tên bảng tuỳ chỉnh.
     */
    protected $table = 'user';

    /**
     * Khoá chính tuỳ chỉnh.
     */
    protected $primaryKey = 'ID_User';

    /**
     * Không dùng timestamps mặc định của Laravel (created_at / updated_at).
     * Bảng user chỉ có ngaydangki.
     */
    public $timestamps = false;

    /**
     * Các trường cho phép mass-assignment.
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
    ];

    /**
     * Các trường ẩn khi serialize (trả về JSON).
     *
     * @var list<string>
     */
    protected $hidden = [
        'matkhau',
    ];

    /**
     * Kiểu dữ liệu cast cho các trường.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // KHÔNG dùng 'hashed' vì AuthController dùng Hash::make() thủ công
            // Nếu để 'hashed' sẽ bị double-hash → login thất bại
            'TrangThai'  => 'integer',
            'ngaydangki' => 'datetime',
        ];
    }

    /**
     * Trỏ tới trường mật khẩu thực tế trong bảng.
     * Sanctum / Auth dùng getAuthPassword() → cần override.
     */
    public function getAuthPassword(): string
    {
        return $this->matkhau;
    }

    /**
     * Quan hệ: User thuộc về một Role.
     */
    public function role()
    {
        return $this->belongsTo(Role::class, 'ID_role', 'ID_role');
    }

    /**
     * Quan hệ: User có một Shop (NguoiBan).
     */
    public function shop()
    {
        return $this->hasOne(Shop::class, 'ID_User', 'ID_User');
    }

    /**
     * Helper: kiểm tra role theo tên.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->Ten_role === $roleName;
    }

    /**
     * Helper: kiểm tra role theo ID.
     */
    public function hasRoleId(int $roleId): bool
    {
        return (int) $this->ID_role === $roleId;
    }
}
