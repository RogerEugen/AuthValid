<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'faculty_id',
        'name',
        'code',
        'hod_user_id',
        'is_active'
        ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function hod()
    {
        return $this->belongsTo(User::class, 'hod_user_id');
    }

    public function programs()
    {
        return $this->hasMany(Program::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}