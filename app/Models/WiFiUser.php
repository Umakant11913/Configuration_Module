<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WiFiUser extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'phone',
        'email',
        'mac_address',
        'password'
    ];
}
