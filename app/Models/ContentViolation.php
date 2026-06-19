<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentViolation extends Model
{
    protected $fillable = [
        'user_id',
        'user_role',
        'content_fingerprint',
        'sequence',
        'student_affairs_review',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'student_affairs_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
