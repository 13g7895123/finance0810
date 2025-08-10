<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'default_permissions',
        'is_system_role',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'default_permissions' => 'array',
        'is_system_role' => 'boolean',
    ];

    /**
     * Get role display name for frontend
     */
    public function getDisplayNameAttribute($value)
    {
        return $value ?: ucfirst($this->name);
    }

    /**
     * Check if role is system role (cannot be deleted)
     */
    public function isSystemRole(): bool
    {
        return $this->is_system_role;
    }

    /**
     * Get role with Chinese translations
     */
    public static function getRoleTranslations(): array
    {
        return [
            'admin' => '經銷商/公司高層',
            'executive' => '經銷商/公司高層', // Alternative name for admin
            'manager' => '行政人員/主管',
            'staff' => '業務人員',
        ];
    }

    /**
     * Get translated display name
     */
    public function getTranslatedName(): string
    {
        $translations = self::getRoleTranslations();
        return $translations[$this->name] ?? $this->display_name ?? ucfirst($this->name);
    }
}