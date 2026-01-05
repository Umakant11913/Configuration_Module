<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneInternetPlan extends BaseModel
{
    public $timestamps = false;

    public function internetPlans()
    {
        return $this->hasMany(InternetPlan::class,  'id', 'internet_plan_id');
    }
    public function brandingProfiles()
    {
        return $this->hasMany(BrandingProfile::class,  'id', 'branding_profile_id');
    }

}
