<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'pdo_owner_id',
        'payout_date',
        'payout_amount',
        'payout_status',
        'payout_calculation_date',
        'payout_type',
        'plan_amount'
    ];
}
