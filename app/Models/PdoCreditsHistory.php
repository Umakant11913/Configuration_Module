<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoCreditsHistory extends Model
{
    use HasFactory;
    protected $table = 'pdo_credits_history';

    protected $fillable = [
        'pdo_id',
        'credits',
    ];
}
