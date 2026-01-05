<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banners extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'impressions',
        'image',
        'lat',
        'lng',
        'impression_counts',
        'expiry_date',
        'status',
        'video',
        'link',
        'timer',
        'page_type',
        'send_summary_report',
        'summary_option',
        'clicks',
        'clicks_count'
    ];
    protected $casts = [
        'page_type' => 'json',
    ];
    public function locationZoneBannerAds()
    {
        return $this->hasMany(PdoLocationZoneBannerAds::class, 'pdo_banner_id');
    }
    public function impressions()
    {
        return $this->hasMany(PdoBannerImpressions::class, 'pdo_banner_id');
    }
    public function clicks()
    {
        return $this->hasMany(PdoBannerImpressions::class, 'pdo_banner_id');
    }

    public function pdoLocationZoneBannerAd()
    {
        return $this->belongsTo(PdoLocationZoneBannerAds::class, 'pdo_banner_id', 'pdo_banner_id');
    }
}

