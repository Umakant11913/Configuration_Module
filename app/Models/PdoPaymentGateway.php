<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoPaymentGateway extends Model
{
    use HasFactory;

    public $table = 'pdo_payment_gateway';

    protected $fillable= [
        'pdo_id',
        'secret',
        'key',
        'zone_id',
        'providers'

    ];
}
