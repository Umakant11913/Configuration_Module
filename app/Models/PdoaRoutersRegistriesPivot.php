<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoaRoutersRegistriesPivot extends Model
{
    use HasFactory;

    public $table = 'pdoa_routers_registries_pivot';

    public $timestamps = false;

    protected $fillable = [
        'pdoa_registry_id',
        'pdoa_router_registry_id'
    ];
}
