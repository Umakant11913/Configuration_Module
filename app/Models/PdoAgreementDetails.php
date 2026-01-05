<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoAgreementDetails extends Model
{
    use HasFactory;
    protected $fillable = [
        'pdo_owner_id',
        'router_id',
        'revenue_share',
        'subscription_data',
        'sms_quota',
        'storage_quota',
        'email_quota',
        'latitude',
        'longitude',
        'expiry_date',
        'start_date',
        'end_date',
        'agreement_status'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'pdo_owner_id');
    }
}
