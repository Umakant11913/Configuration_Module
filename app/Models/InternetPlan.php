<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternetPlan extends BaseModel
{
    public function zoneInternetPlan()
    {
        return $this->hasMany(ZoneInternetPlan::class,  'internet_plan_id', 'id');
    }

}
