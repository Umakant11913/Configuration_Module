<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MqttApsResponse extends Model
{
    use HasFactory;

    // Fields that can be mass-assigned
    protected $fillable = [
        'mac',
        'json_response',
        'from_response',
    ];
}
