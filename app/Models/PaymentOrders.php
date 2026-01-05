<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentOrders extends Model
{
    use HasFactory;
    protected $fillable = [
        'internet_plan_id',
        'wifi_user_id',
        'franchise_id',
        'status',
        'amount'
    ];
}
