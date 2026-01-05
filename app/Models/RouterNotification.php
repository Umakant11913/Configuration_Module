<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouterNotification extends Model
{
    use HasFactory;
    protected $table = 'router_notifications';
    protected $fillable = ['user_id', 'status', 'notification_type'];


}
