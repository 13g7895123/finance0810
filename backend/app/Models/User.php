<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
        'name',
        'avatar',
        'status',
        'preferences',
        'line_user_id',
        'line_profile',
        'last_login_at',
        'last_login_ip',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferences' => 'array',
        'line_profile' => 'array',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'status' => 'string',
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }

    /**
     * Check if user is admin or company executive (has all permissions)
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(['admin', 'executive']);
    }

    /**
     * Check if user is manager or admin staff
     */
    public function isManager(): bool
    {
        return $this->hasRole(['admin', 'executive', 'manager']);
    }

    /**
     * Check if user is staff member
     */
    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    /**
     * Get customers assigned to this user
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    /**
     * Get chat conversations for this user
     */
    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class, 'user_id');
    }

    /**
     * Scope to filter active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin($ip = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }
}