<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use HasFactory;
    protected $connection = 'mysql3'; // ✅ use mysql3 connection
    protected $table = 'message_logs';
}
