<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Models extends Model
{
    use HasFactory;

    protected $appends = [
        /*'firmware_file',*/
    ];

    protected $dates = [
        'last_firmware_updated_at',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'name',
        'firmware_version',
        'last_firmware_updated_at',
        'created_at',
        'updated_at',
        'settings',
        'inventory_model_id'
    ];

    public function firmwares()
    {
        return $this->hasMany(ModelFirmwares::class, 'model_id', 'id');
    }

    public function router()
    {
        return $this->hasMany(Router::class, 'model_id');
    }

}
