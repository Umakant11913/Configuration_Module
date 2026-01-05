<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdoaPlan extends BaseModel
{


    public function pdoSmsQuota()
    {
        return $this->hasOne(PdoSmsQuota::class, 'pdo_id')->latest();
    }

    public function pdoCredits()
    {
        return $this->hasOne(PdoCredits::class, 'pdo_id');
    }

    public function users(){
        return $this->hasOne(User::class, 'pdo_type');
    }

}
