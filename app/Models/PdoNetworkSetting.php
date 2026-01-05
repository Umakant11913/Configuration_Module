<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoNetworkSetting extends Model
{
    use HasFactory;

    public $table = 'pdo_network_settings';

    protected $fillable= [
        'pdo_id',
        'essid'

    ];

}
