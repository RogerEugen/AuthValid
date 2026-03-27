<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid', 
        'first_name', 
        'last_name', 
        'email', 
        'phone',
        'password', 
        'role', 
        'department_id', 
        'is_active',
        'must_change_password', 
        'profile_photo', 
        'created_via',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'last_login_at'        => 'datetime',
            'is_active'            => 'boolean',
            'must_change_password' => 'boolean',
            'password'             => 'hashed',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
        });
    }

    // ── JWT required ──────────────────────────────
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'uuid'          => $this->uuid,
            'role'          => $this->role,
            'department_id' => $this->department_id,
        ];
    }

    // ── Relationships ─────────────────────────────
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function csvImports()
    {
        return $this->hasMany(CsvImportLog::class, 'uploaded_by');
    }
}