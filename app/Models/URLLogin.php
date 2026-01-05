<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class URLLogin extends Model
{
    use HasFactory;
    protected $table = 'u_r_l_logins';
    protected $fillable = [
        'url_code',
        'user_id'
    ];
}
