<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionLog extends Model
{
    use HasFactory;
    protected $table = 'user_session_logs';

    protected $fillable = [
	'session_id',
        'session_start_time',
        'session_update_time',
        'session_type',
        'session_duration',
        'downloads',
        'uploads',
        'plan_id',
        'plan_price',
        'plan_duration',
        'paymnent_id',
        'payment_amount',
        'username',
        'payout_ratio',
        'payout_session_duration_ratio',
        'payout_data_ratio',
        'payout_amount',
        'location_id',
        'plan_data',
        'location_owner_id',
        'total_data_usage',
        'payoutsId',
    ];
}
