<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'pdo_id',
        'periods_quota',
        'periods_type',
        'add_on_available'
    ];

    public function pdoSettings()
    {
        return $this->hasOne(PdoSettings::class);
    }
}
