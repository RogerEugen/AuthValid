<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = [
        'department_id', 
        'name', 
        'code',
        'level', 
        'duration_years', 
        'duration_display', 
        'is_active',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function students()
    {
        return $this->hasMany(StudentProfile::class);
    }
}