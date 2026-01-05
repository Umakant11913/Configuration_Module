<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoLocationZoneBannerAds extends Model
{
    use HasFactory;

    protected $table = 'pdo_location_zone_banners_ads';

    protected $fillable = [
        'pdo_banner_id',
        'location_id',
        'zone_id'
    ];

    public function banner()
    {
        return $this->belongsTo(Banners::class, 'pdo_banner_id');
    }
    public function banners()
    {
        return $this->hasMany(Banners::class, 'id', 'pdo_banner_id');
    }
}
