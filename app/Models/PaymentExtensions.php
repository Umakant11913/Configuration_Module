<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentExtensions extends Model
{
    use HasFactory;
    protected $table = 'payment_extensions';
    protected $fillable = [
        'extensions',
        'extension_date',
        'user_id'
    ];
}
