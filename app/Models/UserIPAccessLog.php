<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserIPAccessLog extends Model
{
    use HasFactory;
    protected $connection = 'mysql3'; // ✅ use mysql3 connection
	protected $table = 'user_i_p_access_logs';
    protected $fillable = [
        'src_ip',
        'dest_ip',
        'protocol',
        'port',
        'src_port',
        'dest_port',
        'client_device_ip',
        'client_device_ip_type',
        'username',
        'client_device_translated_ip',
        'location_id',
        'router_id',
        'user_mac_address'
    ];
    public function location()
    {
        return $this->belongsTo(Location::class);
    }
    public function router()
    {
        return $this->belongsTo(Router::class);
    }
}
