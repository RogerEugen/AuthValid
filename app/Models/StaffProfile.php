<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id', 
        'staff_number', 
        'title', 
        'gender',
        'date_of_birth', 
        'nationality', 
        'specialization',
        'employment_type', 
        'office_location', 
        'joined_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}