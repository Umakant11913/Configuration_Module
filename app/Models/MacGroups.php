<?php

namespace App\Models;


class MacGroups extends BaseModel
{
    public function pdo()
    {
        return $this->belongsTo(User::class, 'pdo_id');
    }
}
