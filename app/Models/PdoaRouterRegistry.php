<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoaRouterRegistry extends Model
{
    use HasFactory;

    public $table = 'pdoa_router_registries';

    protected $fillable = [
        'name',
        'state',
        'geoLoc',
        'macid',
        'ssid',
        'status',
        'pdoa_registry_id',
    ];

    public function PdoaRegistries()
    {
        return $this->belongsTo(PdoaRegistry::class);
    }

}
