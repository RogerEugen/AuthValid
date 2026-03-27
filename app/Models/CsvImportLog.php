<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CsvImportLog extends Model
{
    protected $fillable = [
        'uuid', 
        'uploaded_by', 
        'import_type', 
        'original_filename',
        'stored_filename', 
        'total_rows', 
        'successful_rows',
        'failed_rows', 
        'status', 
        'processed_at', 
        'notes',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($log) {
            $log->uuid = (string) Str::uuid();
        });
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}