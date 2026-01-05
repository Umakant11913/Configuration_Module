<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelFirmwares extends Model
{
    use HasFactory;

    protected $fillable = [
        'firmware_version',
        'firmware_file',
        'released'
    ];

    public function model()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }
}
