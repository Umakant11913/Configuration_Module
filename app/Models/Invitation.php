<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Invitation extends Model
{
    use HasFactory;
    use Notifiable;

    public $table = 'invitations';

    protected $fillable = [
        'email',
        'status',
        'token',
        'roles',
        'created_by'
    ];

}
