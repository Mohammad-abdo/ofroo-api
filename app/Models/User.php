<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'language',
        'role_id',
        'email_verified_at',
        'otp_code',
        'otp_expires_at',
        'last_location_lat',
        'last_location_lng',
        'is_blocked',
        'country',
        'city',
        'gender',
        'city_id',
        'governorate_id',
        'avatar',
        'notifications_enabled',
        'email_notifications',
        'push_notifications',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'password' => 'hashed',
            'last_location_lat' => 'decimal:7',
            'last_location_lng' => 'decimal:7',
            'is_blocked' => 'boolean',
            'notifications_enabled' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
        ];
    }

    /**
     * Get the role that owns the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the merchant profile for the user.
     */
    public function merchant()
    {
        return $this->hasOne(Merchant::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the coupons for the user.
     */
    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Get the reviews for the user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get offers favorited by the user (المحفوظات).
     */
    public function favoriteOffers()
    {
        return $this->belongsToMany(Offer::class, 'offer_user');
    }

    /**
     * Get the cart for the user.
     */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role ? $this->role->name === 'admin' : false;
    }

    /**
     * Check if user is merchant
     */
    public function isMerchant(): bool
    {
        return $this->role ? $this->role->name === 'merchant' : false;
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->role ? $this->role->name === 'user' : false;
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->isAdmin()) {
            return true; // Admin has all permissions
        }

        return $this->role ? ($this->role->hasPermission($permissionName) ?? false) : false;
    }


    /**
     * Get loyalty account
     */
    public function loyaltyAccount()
    {
        return $this->hasOne(LoyaltyPoint::class);
    }

    /**
     * Get loyalty transactions
     */
    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    /**
     * Get support tickets
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get devices
     */
    public function devices()
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Get 2FA settings
     */
    public function twoFactorAuth()
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    /**
     * Get activity logs
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
