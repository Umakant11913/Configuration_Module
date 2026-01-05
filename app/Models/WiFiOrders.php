<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class WiFiOrders extends BaseModel
{
    use HasFactory;

    protected $appends = ['date'];

    public function getDateAttribute()
    {
        return $this->created_at;
    }

    public function internetPlan()
    {
        return $this->belongsTo(InternetPlan::class, 'internet_plan_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
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
        $authId = $user->id;
        $query->where('owner_id', $authId)->orWhereHas('location', function ($query) use ($authId) {
            $query->where('owner_id', $authId);
        });
        return $query;
    }

    public function scopeForLocations($query, $locations)
    {
        $query->whereIn('location_id', $locations);
        return $query;
    }

    public function scopeForDays($query, Carbon $date, $days = 30)
    {
        $endDate = $date->clone();
        $startDate = $date->clone()->subDays($days);
        $query->whereDate('created_at', '>=', $startDate);
        $query->whereDate('created_at', '<=', $endDate);
        return $query;
    }
}
