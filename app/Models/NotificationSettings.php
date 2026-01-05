<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdo_id',
        'status',
        'notification_type',
        'frequency',
        'weekly_day',
        'time',
        'date',
        'channel',
        'recipient_id'

    ];
    // If recipient_id is a JSON array containing multiple user IDs
    public function recipients()
    {
        // Use custom relation since recipient_id is a JSON field with multiple values
        return $this->belongsToMany(User::class, 'recipient_id', 'id');
    }
}
