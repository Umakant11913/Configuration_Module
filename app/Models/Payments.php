<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payments extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference_id',
        'internet_plan_id',
        'order_id',
        'payment_method',
        'wifi_user_id',
        'franchise_id',
        'payment_status',
        'amount'
    ];

    public function scopeForMonth($query, Carbon $date)
    {
        $startDate = $date->clone()->startOfMonth();
        $endDate = $date->clone()->endOfMonth();
        $query->whereDate('created_at', '>=', $startDate);
        $query->whereDate('created_at', '<=', $endDate);
        return $query;
    }
}
