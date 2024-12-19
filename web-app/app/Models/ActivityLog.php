<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log'; // Specify the table name if it's not pluralized

    protected $fillable = [
        'user_id',
        'action',
        'action_time',
        'ip_address',
        'user_agent',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public $timestamps = true; // This will allow the created_at and updated_at columns to be managed by Eloquent
}
