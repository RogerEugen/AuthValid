<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id',
        'registration_number', 
        'program_id', 
        'year_of_study', 
        'semester', 
        'academic_year', 
        'gender',
        'date_of_birth', 
        'nationality', 
        'admission_year', 
        'enrollment_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    // Derived relationship (important) //thisn help us to get the deprtiment of the students 
    //StudentProfile::with('program.department.faculty')->get(); we can you this to get the department of the student
    //when you need department info on a list of students, load it like this to avoid N+1 queries
    public function department()
    {
        return $this->program->department();
    }
}