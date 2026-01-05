<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WiFiStatus extends Model
{
    use HasFactory;

    protected $table = 'wi_fi_statuses';
    protected $fillable = [
        'wifi_router_id',
        'cpu_usage',
        'ram_usage',
        'disk_usage',
        'latest_version',
        'network_speed',
        'client_2g',
        'client_5g',
        'client_details'
    ];

    protected $casts = ['cpu_usage' => 'float', 'client_details' => 'array'];
}
