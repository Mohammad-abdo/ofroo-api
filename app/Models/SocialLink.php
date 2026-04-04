<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    protected $fillable = [
        'platform',
        'url',
    ];

    /** Request key suffix e.g. instagram_url */
    public const PLATFORM_TO_REQUEST_KEY = [
        'instagram' => 'instagram_url',
        'facebook' => 'facebook_url',
        'twitter' => 'twitter_url',
        'youtube' => 'youtube_url',
        'snapchat' => 'snapchat_url',
        'telegram' => 'telegram_url',
        'tiktok' => 'tiktok_url',
        'whatsapp' => 'whatsapp_url',
    ];
}
