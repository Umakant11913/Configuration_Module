<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Router extends BaseModel
{

    protected $dates = ['lastOnline'];

    protected $appends = ['isOnline'];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }
    public function wifiConfigurationProfile()
    {
        return $this->belongsTo(WifiConfigurationProfiles::class, 'wifi_configuration_profile_id');
    }

    public function scopeUnassigned($query, $with_location)
    {
        $query->whereNull('location_id');
        if ($with_location) {
            $query->orWhere('location_id', $with_location);
        }
        return $query;
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
        $query->where('owner_id', $user->id);
        return $query;
    }

    public function scopeOnline($query)
    {
        return $query
            ->whereNotNull('lastOnline')
            ->where('lastOnline', '>=', Carbon::now()->subMinutes(3)->toDateTimeString());
    }

    public function getIsOnlineAttribute()
    {
        if (!$this->lastOnline) {
            return false;
        }
        return $this->lastOnline->gte(Carbon::now()->subMinutes(3));
    }

    public function model()
    {
        return $this->belongsTo(Models::class, 'model_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }



}
