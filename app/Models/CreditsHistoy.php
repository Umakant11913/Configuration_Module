<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditsHistoy extends Model
{
    use HasFactory;
    protected $table = 'credits_history';

    protected $fillable = [
        'pdo_id',
        'router_id',
        'type',
        'pdo_credits_id',
        'credit_used'
    ];
}
