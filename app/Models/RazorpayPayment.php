<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RazorpayPayment extends Model
{
    use HasFactory;
    protected $fillable = [
        'payment_id', 'wifi_user_id','amount'
    ];

}
