<?php

namespace App\Models;


class WifiConfigurationProfiles extends BaseModel
{
    public function pdo()
    {
        return $this->belongsTo(User::class, 'pdo_id');
    }
    public function locations()
    {
        return $this->hasMany(Location::class, 'id', 'wifi_configuration_profile_id');
    }
}
