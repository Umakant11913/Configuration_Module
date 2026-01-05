<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignPdoRequest extends Model
{
    use HasFactory;

    protected $table = 'assign_pdo_request';

    protected $fillable = [
        'id',
        'from_id',
        'to_id',
        'status',
        'user_id',
    ];

    public function fromUser() {
        return $this->belongsTo(User::class, 'from_id');
    }

    public function toUser() {
        return $this->belongsTo(User::class, 'to_id');
    }
    
}
