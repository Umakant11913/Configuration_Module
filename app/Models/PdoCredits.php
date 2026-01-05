<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoCredits extends Model
{
    use HasFactory;
    protected $fillable = [
        'pdo_id',
        'credits',
        'used_credits',
        'default_credits',
        'expiry_date',
        'auto_renew_subscription',
        'type',
        'grace_credits'
    ];
}
