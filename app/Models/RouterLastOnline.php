<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouterLastOnline extends Model
{
    use HasFactory;

    protected $table = 'router_last_onlines';
    protected $fillable = ['pdo_id', 'last_Online', 'notification_type'];
}
