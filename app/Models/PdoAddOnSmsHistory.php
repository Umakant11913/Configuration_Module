<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoAddOnSmsHistory extends Model
{
    use HasFactory;

    protected $table = 'pdo_add_on_sms_history';

    protected $fillable = [
        'pdo_id',
        'sms_credits',
        'type',
    ];
}
