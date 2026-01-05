<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroups extends Model
{
    public $table = 'wifi_configuration_users';

    use HasFactory;

    protected $fillable = [
        'user_id',
        'pdo_id',
        'full_name',
        'description',
        'password',
        'email',
        'expiry_date',
        'type',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'expiry_date'
    ];

}
