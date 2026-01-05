<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppLoginLog extends Model
{
    use HasFactory;
 protected $table = 'app_login_logs';
    protected $fillable = [
        'username',
        'app_id'
    ];

}
