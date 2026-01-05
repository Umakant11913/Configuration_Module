<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoSmsQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdo_id',
        'quota_id',
        'sms_used',
        'sms_quota',
        'add_on_sms',
        'default_sms',
        'carry_forward_sms',
        'type'
    ];

}
