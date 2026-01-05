<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class wifiOTP extends Model
{
    use HasFactory;
    protected $table = 'wifi_otps';
    protected $fillable = [
        'phone',
        'otp',
        'mac_address',
        'status',
        'url_code',
        'challenge'
    ];
}
