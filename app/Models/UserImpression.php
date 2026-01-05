<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserImpression extends Model
{
    use HasFactory;
    protected $table = 'user_impressions';

    protected $fillable = [
        'user_id',
        'phone',
        'mac_address',
        'clicks',
        'impression',
        'banner_id',
    ];
}
