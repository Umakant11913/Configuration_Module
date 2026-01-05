<?php

namespace App\Models;

trait HasKeys
{
    public function keys()
    {
        return $this->morphMany(PublicKey::class, 'keyable');
    }

    public function key()
    {
        return $this->keys()->latest();
    }
}
