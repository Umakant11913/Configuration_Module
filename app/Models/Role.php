<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    public $table = 'roles';

    protected $fillable = [
        'title',
        'global_role',
        'system_role',
        'created_by'
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
