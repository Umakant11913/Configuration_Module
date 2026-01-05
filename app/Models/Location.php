<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Location extends BaseModel
{
    protected $casts = [
        'lat' => 'float',
        'lng' => 'float'
    ];

    public function routers()
    {
        return $this->hasMany(Router::class, 'location_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function profile()
    {
        return $this->belongsTo(BrandingProfile::class, 'profile_id');
    }
    public function scopeOfOwner($query, $user)
    {
        $id = $user instanceof Model ? $user->id : $user;
        return $query->where('owner_id', $id);
    }

    public function scopeMine($query)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        if ($user && $user->isAdmin()) {
            return $query;
        }
        return $query->ofOwner($user->id);
    }

    public function wifiConfigurationProfileLocation()
    {
        return $this->belongsTo(WifiConfigurationProfiles::class, 'wifi_configuration_profile_id');
    }
}
