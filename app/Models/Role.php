<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $attributes = [
        'guard_name' => 'web',
    ];

    protected static function booted(): void
    {
        static::creating(function (Role $role) {
            if (empty($role->guard_name)) {
                $role->guard_name = 'web';
            }
        });
    }

    protected $fillable = [
        'name',
        'guard_name',
        'name_ar',
        'name_en',
        'description',
        'description_ar',
        'description_en',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    /**
     * Get the users for the role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the permissions for the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /**
     * Check if role has permission
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }
}