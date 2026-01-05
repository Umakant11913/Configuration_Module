<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoBankDetails extends Model
{
    use HasFactory;
    protected $fillable = [
        'pdo_owner_id',
        'name',
        'bank_name',
        'account_number',
        'branch',
        'account_type',
        'ifsc_code',
        'bank_status',
        'is_primary'
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'pdo_owner_id');
    }
}
