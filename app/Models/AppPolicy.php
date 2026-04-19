<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppPolicy extends Model
{
    protected $table = 'app_policies';

    /** Canonical section types used by the mobile app. */
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_ABOUT = 'about';
    public const TYPE_SUPPORT = 'support';

    public const TYPES = [
        self::TYPE_PRIVACY,
        self::TYPE_ABOUT,
        self::TYPE_SUPPORT,
    ];

    protected $fillable = [
        'type',
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'order_index',
        'is_active',
    ];

    protected $attributes = [
        'type' => self::TYPE_PRIVACY,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    /**
     * Scope: rows of a given section type (privacy|about|support).
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
