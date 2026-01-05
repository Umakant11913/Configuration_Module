<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class thresholdLimitNotification extends Model
{
    use HasFactory;
    protected $fillable = [
        'pdo_id',
        'payout_id',
        'first_name',
        'last_name',
        'subject',
        'body',
        'payout_amount',
        'mail_sent',
        'approved_status',
        'payment_status'
    ];
}
