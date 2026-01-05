<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MqttApsLiveStatus extends Model
{
    use HasFactory;
    // protected $table = 'mqtt_aps_live_status';

    // Fields that can be mass-assigned
    protected $fillable = [
        'mac',
        'status',
        'json_data',
        'setting_update_at',
    ];
}
