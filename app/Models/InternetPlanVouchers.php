<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternetPlanVouchers extends Model
{
    use HasFactory;

    public $table = 'internet_plans_vouchers';

    protected $fillable = [
        'title',
        'status',
        'expiry_date',
        'location_id',
        'plan_id',
        'pdo_id',
        'user_id',
        'zone_id',
        'used_on',
    ];

    public function internetPlans()
    {
        return $this->belongsTo(InternetPlan::class, 'plan_id');
    }

    public function users()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function owners()
    {
        return $this->belongsTo(User::class,'pdo_id');
    }

    public function locations()
    {
        return $this->belongsTo(Location::class,'location_id');
    }

    public function zone()
    {
        return $this->belongsTo(BrandingProfile::class,'zone_id');
    }
}
