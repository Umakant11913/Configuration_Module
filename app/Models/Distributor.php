<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'renewal_date',
        'distributor_plan',
        'contract',
        'exclusive',
        'gst_type',
        'gst_no'
    ];

    public function scopeDistributorOwners($query)
    {
        return $query->where('role', config('constants.roles.distributor'));
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function distributorPlan()
    {
        return $this->belongsTo(DistributorPlan::class, 'distributor_plan');
    }


}
