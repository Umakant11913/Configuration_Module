<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DistributorPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'area_name',
        'number_of_area',
        'number_of_device',
        'target_type',
        'target_device',
        'pdo_id'
    ];
}
