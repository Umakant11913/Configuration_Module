<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoBannerImpressions extends Model
{
    use HasFactory;

    protected $table = 'pdo_banner_impressions';

    protected $fillable = [
        'pdo_banner_id',
        'location_id',
        'zone_id',
        'impressions',
        'clicks'
    ];


    public function banner()
    {
        return $this->belongsTo(Banners::class, 'id','pdo_banner_id');
    }

}
