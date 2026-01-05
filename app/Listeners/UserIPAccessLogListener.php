<?php

namespace App\Listeners;

use App\Models\Router;
use App\Models\UserIPAccessLog;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\UserIPAccessLogEvent;
use Illuminate\Support\Facades\Log;
use DB;


class UserIPAccessLogListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(UserIPAccessLogEvent $event)
    {
        $requestData = $event->requestData;
        $routerKey = $event->routerKey;
        $getUserIpAddr = $event->userIpAddr;

        $router = Router::where('key',$routerKey)->first();
        $router_mac = $router->mac_address;
        $location_id = $router->location_id;
        $router_id = $router->id;

        try{
            if(isset($requestData['LEGACY_MSGHDR']) && (($requestData['LEGACY_MSGHDR'] == '[NEW]') || ($requestData['LEGACY_MSGHDR'] == '[DESTROY]'))){
                $data = explode (' ', $requestData['MESSAGE']);
                $src_ip = substr($data[1],4);

                if(substr($src_ip,0,10) == '172.22.100'){
                    $user_info = DB::connection('mysql2')->table('radacct')
                        ->where('framedipaddress', $src_ip)
                        ->where('location_id', $location_id)
                        ->orderBy('radacctid','desc')
                        ->first();

                    $new_log = array();

                    $new_log['src_ip'] = $src_ip;
                    $new_log['dest_ip'] = substr($data[2],4);
                    $new_log['username'] = $user_info && $user_info->username ? substr($user_info->username,0,10) : "N/A";
                    $new_log['port'] = substr($data[5],4);
                    $new_log['protocol'] = substr($data[3],6);
                    $new_log['client_device_translated_ip'] = $getUserIpAddr;
                    $new_log['client_device_ip'] = $user_info && $user_info->nasipaddress ? $user_info->nasipaddress : substr($data[1],4);
                    $new_log['src_port'] = substr($data[4],4);
                    $new_log['dest_port'] = substr($data[5],4);
                    $new_log['client_device_ip_type'] = 'dynamic';
                    $new_log['location_id'] = $location_id;
                    $new_log['router_id'] = $router_id;
                    $new_log['user_mac_address'] = $user_info->callingstationid;

                    if($requestData['LEGACY_MSGHDR'] == '[NEW]' && substr($new_log['src_ip'],0,10) == '172.22.100'){
                        $add_new_log = UserIPAccessLog::create($new_log);
                    }

                    if($requestData['LEGACY_MSGHDR'] == '[DESTROY]'){
                        $old_record_new_log = UserIPAccessLog::where('src_ip',$new_log['src_ip'])->where('dest_ip',$new_log['dest_ip'])->first();
                        if($old_record_new_log){
                            $old_record_new_log->updated_at = Carbon::now();
                            $old_record_new_log->save();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Exception caught in ip_logging method: ' . $e->getMessage());
            return ;
        }
    }
}
