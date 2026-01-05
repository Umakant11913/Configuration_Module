<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsHistory extends Model
{
    use HasFactory;
    protected $table = 'pdo_sms_history';
    protected $fillable = [
        'pdo_id',
        'user_id',
        'phone',
        'quota_id'
    ];

}
