<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnonymousToken extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'token_hash', 
        'role', 
        'department_id',
        'expires_at', 
        'is_used', 
        'used_at', 
        'is_revoked',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
            'is_used'    => 'boolean',
            'is_revoked' => 'boolean',
        ];
    }

    public function isValid(): bool
    {
        return !$this->is_used
            && !$this->is_revoked
            && $this->expires_at->isFuture();
    }
}