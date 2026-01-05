<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrandingProfile extends Model
{
    use HasFactory;

    protected $appends = [
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'name',
        'description',
        'logo',
        'banner',
        'created_at',
        'updated_at',
    ];

    public function pdo()
    {
        return $this->belongsTo(User::class, 'pdo_id', 'id');
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'profile_id');
    }

    public function zoneInternetPlan()
    {
        return $this->hasMany(ZoneInternetPlan::class,  'branding_profile_id', 'id');
    }

    public function pdoPaymentGateway()
    {
        return $this->hasOne(PdoPaymentGateway::class,'zone_id');
    }

}
