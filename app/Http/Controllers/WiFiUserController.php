<?php

namespace App\Http\Controllers;

use App\Models\InternetPlan;
use App\Models\Location;
use App\Models\User;
use App\Models\WifiConfigurationProfiles;
use App\Models\WiFiOrders;
use Carbon\Carbon;
use Dapphp\Radius\Radius;
use Illuminate\Http\Request;
use App\Models\WiFiUser;
use App\Models\URLLogin;
use App\Models\NetworkSettings;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentExtensions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;



class WiFiUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['info','planstatus','autologin', 'isActiveSession', 'radiusClientAuthentication', 'radiusAuthenticate']]);
    }

    public function info(Request $request)
    {
        $mac = $request->mac;
        $phone = $request->phone;
        $wifiUser = WiFiUser::where('mac_address',$mac)->orWhere('phone', $phone . '--Free')->orWhere('phone', $phone)->orderBy('created_at', 'desc')->first();

        if(! $wifiUser){
            return response()->json([
                'status' => 'failure',
                'message' => 'WiFi user Does not exist',
            ], 201);
        }else{

            return response()->json([
                'status' => 'success',
                'message' => 'WiFi user details retrived Successfully.',
                'data' => $wifiUser,
                'action' => 'Show plans.',
                'show_plan' => 1
            ], 201);


            $now = time();
            $phone = $wifiUser->phone;
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->where('username',$phone)->where('expiration_time','>',$now)->first();

            if(!$radius_wifiuser ){
                return response()->json([
                    'status' => 'success',
                    'message' => 'WiFi user details retrived Successfully.',
                    'data' => $wifiUser,
                    'action' => 'Show plans.',
                    'show_plan' => 1
                ], 201);
            }else{
                return response()->json([
                    'status' => 'success',
                    'message' => 'WiFi user details retrived Successfully',
                    'data' => $wifiUser,
                    'action' => 'Do not show plan.',
                    'show_plan' => 0
                ], 201);
            }

        }
    }
    public function planstatus(Request $request)
    {
        $phone = $request->phone;
        $now = time();
        $free_access_available = 1;
        $extension_data_available = 1;
        $today = date("Y-m-d");
        $data = NetworkSettings::select('free_download')->where('id', 1)->first();

        $user = User::where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        try{
            $free_data_usage = DB::connection('mysql2')->table('radacct')
                ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                //->where('username', $phone . '--Free')
                ->where('username', $phone)
                ->where('start_date', ">=",$today)
                ->get();
            $total_free_download = $free_data_usage[0]->downloads + $free_data_usage[0]->uploads;
            //$total_free_download = 101;
        }
        catch (\Exception $e) {
            $total_free_download = 0;
        }
        $max_available_download = $data->free_download;
        //$max_extension_download_available = $data->free_download+2*30;
        $max_extension_download_available = $data->free_download;

        if($total_free_download >= $max_available_download){
            $free_access_available = 0;
        }
        if($total_free_download >= $max_extension_download_available) {
            $extension_data_available = 0;
        }

        $radWiFiUser = DB::connection('mysql2')->table('radcheck')->where('username', $phone)->first();
        // $radWiFiUser = DB::connection('mysql2')->table('radcheck')->where('phone_number', $phone)->first();

        if(!$radWiFiUser ){
            return response()->json([
                'status' => 'false',
                'message' => 'WiFi Session Does not exist',
                'action' => 'Do not show plan.',
                'show_plan' => 0
            ], 201);
        }else{
            if ($radWiFiUser->expiration_time > $now) {
                $wifiOrders = WiFiOrders::where('status', '1')->where('phone', $phone)->where('data_used', false)->orderBy('created_at', 'ASC')->get();

                if ($wifiOrders) {
                    $internetPlanDataLimit = 0;
                    foreach ($wifiOrders as $wifiOrder) {
                        $internetPlan = InternetPlan::find($wifiOrder->internet_plan_id);
                        $internetPlanDataLimit += $internetPlan->data_limit;

                    }
                }
                $data_available = $internetPlanDataLimit;
                $bandwidth = $radWiFiUser->bandwidth;
                $start_date = $radWiFiUser->plan_start_time;
                
                try{
                    $session_info = DB::connection('mysql2')->table('radacct')
                        ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                        ->where('username', $phone)
                        ->where('start_date', '>=', $start_date)
                        ->get();
                    $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
                }
                catch (\Exception $e) {
                    $total_download = 0;
                }
                if($total_download >=$data_available ){
                    /*if (!$radWiFiUser) {
                        Log::info("Radius check data not found so creating it");
                        $radWiFiUser = \Illuminate\Support\Facades\DB::connection('mysql2')->table('radcheck')
                            ->insert([
                                'username' => $phone ?? $request->mac_address,
                                'value' => $phone ?? $request->mac_address,
                                'attribute' => 'Cleartext-Password',
                                'op' => ':=',
                                'data_limit' => '-1',
                                'session_duration' => '5',
                                'bandwidth' => '20480',
                                'expiration_time' => $now,
                                'phone_number' => $user->phone,
                                'name' => $user->first_name.' '.$user->last_name
                            ]);

                    } else {
                        Log::info("Radius check data found so updating it");
                        $radWiFiUser = DB::connection('mysql2')->table('radcheck')
                            ->where('username', $phone ?? $request->mac_address)
                            ->update([
                                'phone_number' => $phone,
                                'name' => $user->first_name.' '.$user->last_name,
                                'data_limit' => '-1',
                                'session_duration' => '5',
                                'bandwidth' => '20480',
                                'expiration_time' => $now,
                            ]);
                    }*/
                    return response()->json([
                        'status' => 'false',
                        'message' => 'WiFi Data exhausted',
                        'free_access_available' => $free_access_available,
                        'extension_data_available' => $extension_data_available
                    ], 201);
                }else{
                    $login_url = '';
                    if(! is_null($request->challenge)){
                        if(strlen($request->challenge)> 0){
                            $username = $request->phone;
                            // $password =
                            $wifiuser = DB::connection('mysql2')->table('radcheck')
                                ->where('username', $username)
                                ->first();
                            $password = $wifiuser->value;
                            $challenge = $request->challenge;
                            $uamsecret = '';
                            $hexchal = pack ("H32", $challenge);
                            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
                            $response = md5("\0" .$password . $newchal);
                            $newpwd = pack("a32", $password);
                            $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

                            $login_url = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=';

                        }
                    }

                    return response()->json([
                        'status' => 'true',
                        'message' => 'WiFi Session Available',
                        'expiration_date' => date('Y-m-d', $radWiFiUser->expiration_time),
                        'available'	=>	$data_available,
                        'download'	=>	$total_download,
                        'total_internet_data' => $internetPlanDataLimit,
                        'data_available' => $data_available - $total_download,
                        'free_access_available' => $free_access_available,
                        'extension_data_available' => $extension_data_available,
                        'bandwidth' => $bandwidth,
                        'login_url' => $login_url
                    ], 201);
                }

            }else{
                return response()->json([
                    'status' => 'false',
                    'message' => 'WiFi Session expired',
                    // 'data' => $wifiUser,
                    'action' => 'Do not show plan.',
                    'show_plan' => 0,
                    'free_access_available' => $free_access_available,
                    'extension_data_available' => $extension_data_available,
                ], 201);
            }
        }

    }
    public function oldplanstatus(Request $request)
    {
        $phone = $request->phone;
        $now = time();
        $free_access_available = 1;
        $extension_data_available = 1;
        $today = date("Y-m-d");
        $data = NetworkSettings::select('free_download')->where('id', 1)->first();

        $user = User::where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        try{
            $free_data_usage = DB::connection('mysql2')->table('radacct')
            ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
            //->where('username', $phone . '--Free')
            ->where('username', $phone)
            ->where('start_date', ">=",$today)
            ->get();
            $total_free_download = $free_data_usage[0]->downloads + $free_data_usage[0]->uploads;
            //$total_free_download = 101;
        }
        catch (\Exception $e) {
            $total_free_download = 0;
        }
        $max_available_download = $data->free_download;
        //$max_extension_download_available = $data->free_download+2*30;
        $max_extension_download_available = $data->free_download;

        if($total_free_download >= $max_available_download){
            $free_access_available = 0;
        }
 	if($total_free_download >= $max_extension_download_available) {
            $extension_data_available = 0;
 	}

           $radWiFiUser = DB::connection('mysql2')->table('radcheck')->where('username', $phone)->first();
        // $radWiFiUser = DB::connection('mysql2')->table('radcheck')->where('phone_number', $phone)->first();

        if(!$radWiFiUser ){
            return response()->json([
                'status' => 'false',
                'message' => 'WiFi Session Does not exist',
                'action' => 'Do not show plan.',
                'show_plan' => 0
            ], 201);
        }else{
            if ($radWiFiUser->expiration_time > $now) {
                $data_available = $radWiFiUser->data_limit;
                $bandwidth = $radWiFiUser->bandwidth;
                $start_date = $radWiFiUser->plan_start_time;
                try{
                    $session_info = DB::connection('mysql2')->table('radacct')
                    ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                    ->where('username', $phone)
                    ->where('start_date', '>=', $start_date)
                    ->get();
                    $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
                }
                catch (\Exception $e) {
                    $total_download = 0;
                }
                if($total_download >=$data_available ){
                    /*if (!$radWiFiUser) {
                        Log::info("Radius check data not found so creating it");
                        $radWiFiUser = \Illuminate\Support\Facades\DB::connection('mysql2')->table('radcheck')
                            ->insert([
                                'username' => $phone ?? $request->mac_address,
                                'value' => $phone ?? $request->mac_address,
                                'attribute' => 'Cleartext-Password',
                                'op' => ':=',
                                'data_limit' => '-1',
                                'session_duration' => '5',
                                'bandwidth' => '20480',
                                'expiration_time' => $now,
                                'phone_number' => $user->phone,
                                'name' => $user->first_name.' '.$user->last_name
                            ]);

                    } else {
                        Log::info("Radius check data found so updating it");
                        $radWiFiUser = DB::connection('mysql2')->table('radcheck')
                            ->where('username', $phone ?? $request->mac_address)
                            ->update([
                                'phone_number' => $phone,
                                'name' => $user->first_name.' '.$user->last_name,
                                'data_limit' => '-1',
                                'session_duration' => '5',
                                'bandwidth' => '20480',
                                'expiration_time' => $now,
                            ]);
                    }*/
                    return response()->json([
                        'status' => 'false',
                        'message' => 'WiFi Data exhausted',
                        'free_access_available' => $free_access_available,
                        'extension_data_available' => $extension_data_available
           		 ], 201);
                }else{
                    $login_url = '';
                    if(! is_null($request->challenge)){
                        if(strlen($request->challenge)> 0){
                            $username = $request->phone;
                            // $password =
                            $wifiuser = DB::connection('mysql2')->table('radcheck')
                            ->where('username', $username)
                            ->first();
                            $password = $wifiuser->value;
                            $challenge = $request->challenge;
                            $uamsecret = '';
                            $hexchal = pack ("H32", $challenge);
                            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
                            $response = md5("\0" .$password . $newchal);
                            $newpwd = pack("a32", $password);
                            $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

                            $login_url = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=';

                        }
                    }

                    return response()->json([
                        'status' => 'true',
                        'message' => 'WiFi Session Available',
                        'expiration_date' => date('Y-m-d', $radWiFiUser->expiration_time),
			            'available'	=>	$data_available,
			            'download'	=>	$total_download,
                        'data_available' => $data_available - $total_download,
                        'free_access_available' => $free_access_available,
                        'extension_data_available' => $extension_data_available,
                        'bandwidth' => $bandwidth,
                        'login_url' => $login_url
                    ], 201);
                }

            }else{
                return response()->json([
                    'status' => 'false',
                    'message' => 'WiFi Session expired',
                    // 'data' => $wifiUser,
                    'action' => 'Do not show plan.',
                    'show_plan' => 0,
                    'free_access_available' => $free_access_available,
                    'extension_data_available' => $extension_data_available,
                ], 201);
            }
        }

    }

    public function send_order(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $phone = $user->phone;

        if(strlen($phone) < 10){
            return response()->json([
                'status' => 'false',
                'message' => 'Phone number does not exist or is not verified',
                'user' => $user
            ], 201);
        }

        $user_id = $user->id;
        $today = date("Y-m-d");
        $data = NetworkSettings::first();
        $update_radius = 0;
        $username = $password = $user->phone.'--Free';

        $user_login_url = URLLogin::where('user_id',$user_id)->first();
        $extension_detail = PaymentExtensions::where('user_id',$user_id)->first();
        if($user_login_url){
            $url_code = $user_login_url->url_code;
        }else{
            $url_code = Str::random(6);

            $url_login_info = array();
            $url_login_info['url_code'] = $url_code;
            $url_login_info['user_id'] = $user_id;

            $add_user_url = URLLogin::create($url_login_info);
        }

        if($extension_detail){
            $extension_count = $extension_detail->extensions;
            if(date("Y-m-d", strtotime($extension_detail->extension_date)) == $today){
                if($extension_count >2){
                    try{
                        $session_info = DB::connection('mysql2')->table('radacct')
                        ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                        ->where('username', $username)
                        ->where('start_date', $today)
                        ->get();

                        $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
                    }
                    catch (\Exception $e) {
                        $total_download = 0;
                    }
                    $max_available_download = $data->free_download+2*30;
                    if($total_download >= $max_available_download){
                        return response()->json([
                            'status' => 'false',
                            'message' => 'Extension Exhausted',
                            'user' => $user
                        ], 201);
                    }
                }else{
                    $extension_detail->extensions = $extension_count+1;
                    $extension_detail->save();
                    $free_data = $data->free_download+($extension_count)*30;
                    // Update radcheck
                    Self::update_radius($user->phone, $free_data  );
                    $update_radius = 1;
                }
            }else{
                $extensions = array();
                $extension_detail->extensions = 3;
                $extension_detail->extension_date = $today;
                $extension_detail->save();
                // Update radcheck
                // $data = NetworkSettings::first();

                Self::update_radius($user->phone, $data->free_download);
                $update_radius = 2;
            }
        }else{
            $extensions = array();
            $extensions['user_id'] = $user_id;
            $extensions['extensions'] = 1;
            $extensions['extension_date'] = $today;
            $create_extension = PaymentExtensions::create($extensions);

            // Update radcheck
            Self::update_radius($user->phone, $data->free_download);
            $update_radius = 3;
        }
        $otp = random_int(1000, 9999);
        $url_code = $url_code.'/'.$request->plan_id;

        $send_url = Self::send_sms($otp, $url_code, $user->phone, $request->location_id);
        if(strlen($request->challenge)> 0){
            $challenge = $request->challenge;
            $uamsecret = '';
            $hexchal = pack ("H32", $challenge);
            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
            $response = md5("\0" .$password . $newchal);
            $newpwd = pack("a32", $password);
            $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

            $data = array();

            $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=';
            return response()->json([
                'status' => 'true',
                'message' => 'Login URL Generated',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'login_url' => $data['login_redirect_url'],
                'extension_date' => $extension_detail->extension_date,
                'username' => $username
            ], 201);
        }else{

            return response()->json([
                'status' => 'true',
                'message' => 'Order Sent',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'extension_date' => $extension_detail->extension_date
            ], 201);
        }
    }

//    public function send_order_v2(Request $request)
//    {
//        $user = Auth::user();
//        $phone = $user->phone;
//
//        if(strlen($phone) < 10){
//            return response()->json([
//                'status' => 'false',
//                'message' => 'Phone number does not exist or is not verified',
//                'user' => $user
//            ], 201);
//        }
//
//        $user_id = $user->id;
//        $today = date("Y-m-d");
//        $data = NetworkSettings::first();
//        $update_radius = 0;
//        $username = $password = $user->phone.'--Free';
//        $planId = $request->plan_id;
//        $locationId = $request->location_id;
//
//        $user_login_url = URLLogin::where('user_id',$user_id)->first();
//        $extension_detail = PaymentExtensions::where('user_id',$user_id)->first();
//
//        if($user_login_url){
//            $url_code = $user_login_url->url_code;
//        }else{
//            $url_code = Str::random(6);
//
//            $url_login_info = array();
//            $url_login_info['url_code'] = $url_code;
//            $url_login_info['user_id'] = $user_id;
//
//            $add_user_url = URLLogin::create($url_login_info);
//        }
//            if($extension_detail){
//                $extension_count = $extension_detail->extensions;
//                if(date("Y-m-d", strtotime($extension_detail->extension_date)) == $today){
//                if($extension_count >2){
//                        try{
//                            $session_info = DB::connection('mysql2')->table('radacct')
//                                ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
//                                ->where('username', $username)
//                                ->where('start_date', ">=",$today)
//                                ->get();
//
//                            $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
//                        }
//                        catch (\Exception $e) {
//                            $total_download = 0;
//                        }
//                        $max_available_download = $data->free_download+2*30;
////                    if($total_download >= $max_available_download){
//                        self::update_radius($phone, 0);
//
//                        return response()->json([
//                            'status' => 'true',
//                            'message' => 'Login URL Generated',
//                            'request' => $request->all(),
//                            'user' => $user,
//                            'update_radius' => $update_radius,
//                            'today' => $today,
//                            'login_url' => env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}",
//                            'extension_date' => $extension_detail->extension_date,
//                            'username' => $username
//                        ], 201);
////                    }
//                }else{
//                        $extension_detail->extensions = $extension_count+1;
//                        $extension_detail->save();
//                        $free_data = $data->free_download+($extension_count)*30;
//                        // Update radcheck
//                        Self::update_radius($user->phone, $free_data);
//                        $update_radius = 1;
//                    }
//                } else {
//                    $extensions = array();
//                    $extension_detail->extensions = 1;
//                    $extension_detail->extension_date = $today;
//                    $extension_detail->save();
//                    // Update radcheck
//                    // $data = NetworkSettings::first();
//
//                    Self::update_radius($user->phone, $data->free_download);
//                    $update_radius = 2;
//                }
//            } else {
//                $extensions = array();
//                $extensions['user_id'] = $user_id;
//                $extensions['extensions'] = 1;
//                $extensions['extension_date'] = $today;
//            $extension_detail = PaymentExtensions::create($extensions);
//
//                // Update radcheck
//                Self::update_radius($user->phone, $data->free_download);
//            $update_radius = 3;
//            }
//            $otp = random_int(1000, 9999);
//            $url_code = $url_code.'/'.$request->plan_id;
//
//            // $send_url = Self::send_sms($otp, $url_code, $user->phone, $request->location_id);
//            if(strlen($request->challenge)> 0){
//                $challenge = $request->challenge;
//                $uamsecret = '';
//                $hexchal = pack ("H32", $challenge);
//                $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
//                $response = md5("\0" .$password . $newchal);
//                $newpwd = pack("a32", $password);
//                $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));
//
//                $data = array();
//
//                $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=' . env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}";
//                return response()->json([
//                    'status' => 'true',
//                    'message' => 'Login URL Generated',
//                    'request' => $request->all(),
//                    'user' => $user,
//                    'update_radius' => $update_radius,
//                    'today' => $today,
//                    'login_url' => $data['login_redirect_url'],
//                    'extension_date' => $extension_detail->extension_date,
//                    'username' => $username
//                ], 201);
//            }else{
//
//                return response()->json([
//                    'status' => 'true',
//                    'message' => 'Order Sent',
//                    'request' => $request->all(),
//                    'user' => $user,
//                    'update_radius' => $update_radius,
//                    'today' => $today,
//                        'login_url' => env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}",
//                        'extension_date' => $extension_detail->extension_date,
//                        'username' => $username
//                    ], 201);
//            }
//        }

    public function send_order_v2(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $phone = $user->phone;

        if(strlen($phone) < 10){
            return response()->json([
                'status' => 'false',
                'message' => 'Phone number does not exist or is not verified',
                'user' => $user
            ], 201);
        }

        $user_id = $user->id;
        $today = date("Y-m-d");
        $data = NetworkSettings::first();
        $update_radius = 0;
        $username = $password = $user->phone;
        $planId = $request->plan_id;
        $locationId = $request->location_id;
        $add_on_type = $request->add_on_type;

        $user_login_url = URLLogin::where('user_id',$user_id)->first();
        $extension_detail = PaymentExtensions::where('user_id',$user_id)->first();

        if($user_login_url){
            $url_code = $user_login_url->url_code;
        }else{
            $url_code = Str::random(6);

            $url_login_info = array();
            $url_login_info['url_code'] = $url_code;
            $url_login_info['user_id'] = $user_id;

            $add_user_url = URLLogin::create($url_login_info);
        }

        if($request->freeDataAccess && $request->freeDataAccess !== null){
            $internetPlanData = InternetPlan::where('id', $planId)->first();
            //return $request->freeDataAccess;
            if($extension_detail){
                $extension_count = $extension_detail->extensions;
                if(date("Y-m-d", strtotime($extension_detail->extension_date)) == $today) {
                    if($extension_count > 2){
                        try{
                            $session_info = DB::connection('mysql2')->table('radacct')
                                ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                                ->where('username', $username)
                                ->where('start_date', ">=",$today)
                                ->get();
                            $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
                        }
                        catch (\Exception $e) {
                            $total_download = 0;
                        }
                        //Alert Show on the Captive portal
                        $max_available_download = $internetPlanData->data_limit;
                        if($total_download >= $max_available_download){
                            return response()->json([
                            'status' => 'false',
                            'message' => 'Your Free Internet Data Plan has been Exhausted',
                            'user' => $user
                            ], 201);
                        }
                    } else {
                        $extension_detail->extensions = $extension_count+1;
                        $extension_detail->save();
                       /* Self::update_radius($user->phone, $internetPlanData);*/
                        Self::update_wifi_orders($user->phone, $internetPlanData, $locationId, $add_on_type);
                        Self::processOrder($request , $user );
                        $update_radius = 1;
                    }
                } else {
                    $extensions = array();
                    $extension_detail->extensions = 1;
                    $extension_detail->extension_date = $today;
                    $extension_detail->save();
                    /*Self::update_radius($user->phone, $internetPlanData);*/
                    Self::update_wifi_orders($user->phone, $internetPlanData, $locationId, $add_on_type);
                    Self::processOrder($request , $user );
                    $update_radius = 2;
                }
            } else {
                $extensions = array();
                $extensions['user_id'] = $user_id;
                $extensions['extensions'] = 3;
                $extensions['extension_date'] = $today;
                $extension_detail = PaymentExtensions::create($extensions);
                // Update radcheck
                /*Self::update_radius($user->phone, $internetPlanData);*/
                Self::update_wifi_orders($user->phone, $internetPlanData, $locationId, $add_on_type);
                Self::processOrder($request , $user );
                $update_radius = 1;
            }
            return response()->json([
                'status' => 'true',
                'message' => 'Order Sent',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'login_url' => env('FRONTEND_URL')  . "/packages",
                'extension_date' => $extension_detail->extension_date,
                'username' => $username
            ], 201);

        }

        $otp = random_int(1000, 9999);
        $url_code = $url_code.'/'.$request->plan_id;

        // $send_url = Self::send_sms($otp, $url_code, $user->phone, $request->location_id);
        if(strlen($request->challenge)> 0){
            $challenge = $request->challenge;
            $uamsecret = '';
            $hexchal = pack ("H32", $challenge);
            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
            $response = md5("\0" .$password . $newchal);
            $newpwd = pack("a32", $password);
            $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

            $data = array();

            if($planId != '' || $planId != null){
                $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=' . env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}";
            } else {
                $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=';
            }
            return response()->json([
                'status' => 'true',
                'message' => 'Login URL Generated',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'login_url' => ($planId != '' || $planId != null) ? env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}?add_on_type={$add_on_type}" :  env('FRONTEND_URL')  . "/packages",
                //'extension_date' => $extension_detail->extension_date,
                'username' => $username
            ], 201);
        }else{
            return response()->json([
                'status' => 'true',
                'message' => 'Order Sent',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'login_url' => ($planId != '' || $planId != null) ? env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}?add_on_type={$add_on_type}" :  env('FRONTEND_URL')  . "/packages",
                //'extension_date' => $extension_detail->extension_date,
                'username' => $username
            ], 201);
        }

    }

    public function send_free_order(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $phone = $user->phone;

        if(strlen($phone) < 10){
            return response()->json([
                'status' => 'false',
                'message' => 'Phone number does not exist or is not verified',
                'user' => $user
            ], 201);
        }

        $user_id = $user->id;
        $today = date("Y-m-d");
        $data = NetworkSettings::first();
        $update_radius = 0;
//        $username = $password = $user->phone.'--Free';
        $username = $password = $user->phone;
        $planId = $request->plan_id;
        $locationId = $request->location_id;

        $user_login_url = URLLogin::where('user_id',$user_id)->first();
        $extension_detail = PaymentExtensions::where('user_id',$user_id)->first();

        if($user_login_url){
            $url_code = $user_login_url->url_code;
        }else{
            $url_code = Str::random(6);

            $url_login_info = array();
            $url_login_info['url_code'] = $url_code;
            $url_login_info['user_id'] = $user_id;

            $add_user_url = URLLogin::create($url_login_info);
        }

        if($request->freeDataAccess){
            if($extension_detail){
                $extension_count = $extension_detail->extensions;
                if(date("Y-m-d", strtotime($extension_detail->extension_date)) == $today) {

                    return response()->json([
                        'status' => 'true',
                        'message' => 'Order Sent',
                        'request' => $request->all(),
                        'user' => $user,
                        'update_radius' => $update_radius,
                        'today' => $today,
                        'login_url' => ($planId != '' || $planId != null) ? env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}" :  env('FRONTEND_URL')  . "/packages",
                        'extension_date' => $extension_detail->extension_date,
                        'username' => $username
                    ], 201);

/*                    if($extension_count == 1){
                        try{
                            $session_info = DB::connection('mysql2')->table('radacct')
                                ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                                ->where('username', $username)
                                ->where('start_date', ">=",$today)
                                ->get();

                            $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
                        }
                        catch (\Exception $e) {
                            $total_download = 0;
                        }

                        $max_available_download = $data->free_download;
                        if($total_download >= $max_available_download){
                            self::update_radius($phone, 0);

                            return response()->json([
                                'status' => 'true',
                                'message' => 'Login URL Generated',
                                'request' => $request->all(),
                                'user' => $user,
                                'update_radius' => $update_radius,
                                'today' => $today,
                                'login_url' => ($planId != "" || $planId != null) ? env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}" : env('FRONTEND_URL')  . "/packages",
                                'extension_date' => $extension_detail->extension_date,
                                'username' => $username
                            ], 201);
                        }
                    } else {
                        $extension_detail->extensions = 1;
                        $extension_detail->save();
                        $free_data = $data->free_download;
                        // Update radcheck
                        Self::update_radius($user->phone, $free_data);
                        $update_radius = 1;
                    }*/
                } else {
                    $extensions = array();
                    $extension_detail->extensions = 1;
                    $extension_detail->extension_date = $today;
                    $extension_detail->save();
                    // Update radcheck
                    // $data = NetworkSettings::first();

                    Self::update_radius($user->phone, $data->free_download);
                    $update_radius = 2;
                }
            } else {
                $extensions = array();
                $extensions['user_id'] = $user_id;
                $extensions['extensions'] = 1;
                $extensions['extension_date'] = $today;
                $extension_detail = PaymentExtensions::create($extensions);

                // Update radcheck
                Self::update_radius($user->phone, $data->free_download);
                $update_radius = 1;
            }

        }

        $otp = random_int(1000, 9999);
        $url_code = $url_code.'/'.$request->plan_id;

        // $send_url = Self::send_sms($otp, $url_code, $user->phone, $request->location_id);
        if(strlen($request->challenge)> 0){
            $challenge = $request->challenge;
            $uamsecret = '';
            $hexchal = pack ("H32", $challenge);
            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
            $response = md5("\0" .$password . $newchal);
            $newpwd = pack("a32", $password);
            $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

            $data = array();

            if($planId != '' || $planId != null){
                $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=' . env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}";
            } else {
                $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=';
            }
            return response()->json([
                'status' => 'true',
                'message' => 'Login URL Generated',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'login_url' => ($planId != '' || $planId != null) ? env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}" :  env('FRONTEND_URL')  . "/packages",
                //'extension_date' => $extension_detail->extension_date,
                'username' => $username
            ], 201);
        }else{
            return response()->json([
                'status' => 'true',
                'message' => 'Order Sent',
                'request' => $request->all(),
                'user' => $user,
                'update_radius' => $update_radius,
                'today' => $today,
                'login_url' => ($planId != '' || $planId != null) ? env('FRONTEND_URL')  . "/buy-package/{$planId}/{$locationId}" :  env('FRONTEND_URL')  . "/packages",
                //'extension_date' => $extension_detail->extension_date,
                'username' => $username
            ], 201);
        }

    }

    public function autologin(Request $request)
    {

        $username = $request->username;
        $password = ''; // FreeWiFi-user';
        $wifiuser = DB::connection('mysql2')->table('radcheck')
        ->where('username', $username)
        ->first();
        if($wifiuser){
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
            ->where('username', $username)
            ->first();
            $password = $radius_wifiuser->value;
        }else{
            return response()->json([
                'success' => false,
                'message' => "WiFi Auto-Login Failed",
                "request" => $request->all(),
                "uername" => $request->username,
                "challenge" => $request->challenge
            ], 200);
        }

        $challenge = $request->challenge;
        $uamsecret = '';

        $hexchal = pack("H32", $challenge);
        $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
        $response = md5("\0" . $password . $newchal);
        $newpwd = pack("a32", $password);
        $pappassword = implode('', unpack("H32", ($newpwd ^ $newchal)));

        $data = array();

        $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username=' . $username . '&response=' . $response . '&userurl=';
        $data['response'] = $response;

        return response()->json([
            'success' => true,
            'message' => "WiFi Login Successful",
            "data" => $data,
            "request" => $request
        ], 200);
    }

    public static function send_sms($otp, $url_code, $phone, $location_id = 0)
    {
        // SMS Sending code goes here
        // $phone = '8447578754';
        $phone = '91' . $phone;
        $flow_id = '62974583bd6f912e9902dbc9';
        $flow_id = '62ea1c942b6aa639b304828a';
        $sender_id = 'INTPLW';
        if($location_id != 0){
            $url_code = 'http://login.pmwani.net/b/' . $url_code.'/'.$location_id;
        }else{
            $url_code = 'http://login.pmwani.net/b/' . $url_code;
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.msg91.com/api/v5/flow/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            // CURLOPT_POSTFIELDS => "{\n  \"flow_id\": \"$flow_id\",\n  \"sender\": \"$sender_id\",\n  \"mobiles\": \"$phone\",\n  \"otp\": \"$otp\",\n  \"url\": \"$url_code\"\n    \n}",
            CURLOPT_POSTFIELDS => "{\n  \"flow_id\": \"$flow_id\",\n  \"sender\": \"$sender_id\",\n  \"mobiles\": \"$phone\",\n  \"url\": \"$url_code\"\n    \n}",

            CURLOPT_HTTPHEADER => [
                "authkey: 375442AnKUjTl5f624ed867P1",
                "content-type: application/JSON"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return $response;
        }
    }

    function update_radius($phone, $data){
        $username = $phone;
        $wifiuser = DB::connection('mysql2')->table('radcheck')
        ->where('username', $username)
        ->first();
        if($wifiuser){
            $radUserGroup = $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $username)->first();
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
            ->where('username', $username)
            ->update(array(
                'data_limit' => $data->data_limit,
                'expiration_time' => $data->validity != 0 ? time() + $data->validity*60 : Carbon::now()->addDays(1)->timestamp,
                'bandwidth' => $data->bandwidth,
                'session_duration' => $data->session_duration != 0 ?$data->session_duration : 30,
                'session_duration_window' => $data->session_duration_window ?? 0,
                'plan_start_time' => now()
            ));
            if($radUserGroup) {
                $radUserGroup = DB::connection('mysql2')->table('radusergroup')
                    ->where('username', $username)
                    ->update(array(
                        'username' => $username,
                        'groupname' => 'pmwani',
                        'priority' => '100',
                    ));
            } else {
                DB::connection('mysql2')->table('radusergroup')->insert([
                    'username' => $username,
                    'groupname' => 'pmwani',
                    'priority' => '100',
                ]);
            }
        } else {
            $expiration_time = strtotime('today midnight');
            DB::connection('mysql2')->table('radcheck')->insert([
                'username' => $username,
                'value' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'expiration_time' => $data->validity != 0 ? time() + $data->validity*60 : Carbon::now()->addDays(1)->timestamp,
                'bandwidth' => $data->bandwidth,
                'session_duration' => $data->session_duration != 0 ?$data->session_duration : 30,
                'data_limit' => $data->data_limit,
                'session_duration_window' => $data->session_duration_window ?? 0,
                'plan_start_time' => now()
            ]);
            DB::connection('mysql2')->table('radusergroup')->insert([
                'username' => $username,
                'groupname' => 'pmwani',
                'priority' => '100',
            ]);
        }

        return 1;
    }

    function update_wifi_orders($phone, $data, $locationId, $add_on_type){
        $username = $phone;
        $order = [
            'phone'           =>  $phone,
            'internet_plan_id' =>  $data->id,
            'amount'          =>  $data->price,
            'location_id'     =>  $locationId,
            'payment_status'  =>  'Free',
            'payment_method'  =>  'Free',
            'payment_gateway_type'  =>  'default',
            'status'          => '1',
            'add_on_type'  => $add_on_type,
        ];

        if ($locationId && $locationId != 0) {
            $order['owner_id'] = Location::find($locationId)->owner_id;
        }

        $wifiOrder = WiFiOrders::create($order);

        return 1;
    }

    public function isActiveSession(Request $request)
    {
        $phone = $request->phone;
        $mac = $request->mac_address;
        $user = User::where('phone', $request->phone)->orderBy('created_at', 'desc')->first();

        $active_session= DB::connection('mysql2')->table('radacct')
            ->where('username', $mac ?? $phone)
            ->where('callingstationid', $mac)
            ->where(function ($query) {
                $query->whereNull('acctstoptime');
            })->orderBy('radacctid', 'desc')->first();

        if(!$active_session ){
            return response()->json([
                'status' => 'false',
                'message' => 'Active Session does not exist!',
                'active_session_detail' => $active_session
            ], 201);
        }else{
            return response()->json([
                'status' => 'true',
                'message' => 'Active Session exists!',
                'active_session_detail' => $active_session
            ], 201);
            }
    }

    public function send_login_url(Request $request) {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $phone = $request->phone;
        $mac_address = $request->mac_address;
        $today = date("Y-m-d");

        $radWiFiUserPhone = DB::connection('mysql2')->table('radcheck')->where('username', $phone)->first();
        $radWiFiUserMac = DB::connection('mysql2')->table('radcheck')->where('username', $mac_address)->where('phone_number', $phone)->first();

        $wifiuser = DB::connection('mysql2')->table('radcheck')->where('username', $phone)->first();
        $password = $wifiuser->value;
        $challenge = $request->challenge;
        $uamsecret = '';
        $hexchal = pack ("H32", $challenge);
        $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
        $response = md5("\0" .$password . $newchal);
        $newpwd = pack("a32", $password);
        $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

        if($radWiFiUserPhone) {
            if(!$radWiFiUserMac) {
                DB::connection('mysql2')->table('radcheck')->insert([
                    'username' => $mac_address,
                    'value' => $mac_address,
                    'attribute' => 'Cleartext-Password',
                    'op' => ':=',
                    'expiration_time' => $radWiFiUserPhone->expiration_time,
                    'bandwidth' => $radWiFiUserPhone->bandwidth,
                    'session_duration' => $radWiFiUserPhone->session_duration,
                    'session_duration_window' => $radWiFiUserPhone->session_duration_window,
                    'data_limit' => $radWiFiUserPhone->data_limit,
                    'phone_number' => $radWiFiUserPhone->phone_number,
                    'name' => $radWiFiUserPhone->name,
                    'plan_start_time' => now()
                ]);
            } else {
                DB::connection('mysql2')->table('radcheck')->where('username', $mac_address)
                    ->update([
                        'value' => $mac_address,
                        'expiration_time' => $radWiFiUserPhone->expiration_time,
                        'bandwidth' => $radWiFiUserPhone->bandwidth,
                        'session_duration' => $radWiFiUserPhone->session_duration,
                        'session_duration_window' => $radWiFiUserPhone->session_duration_window,
                        'data_limit' => $radWiFiUserPhone->data_limit,
                        'phone_number' => $radWiFiUserPhone->phone_number,
                        'name' => $radWiFiUserPhone->name,
                        'plan_start_time' => now()
                    ]);
            }
            return response()->json([
                'status' => 'true',
                'message' => 'Login URL Generated',
                'request' => $request->all(),
                'user' => $user,
                'today' => $today,
                'login_url' => 'http://172.22.100.1:3990/logon?username='.$mac_address.'&response='.$response.'&userurl=',
            ], 201);
        }
        return response()->json([
            'status' => 'false',
            'message' => 'Unable to connect to the AP',
            'request' => $request->all(),
            'user' => $user,
            'today' => $today,
        ], 201);

    }

   public function radiusClientAuthentication(Request $request) {
        $location = Location::where('id', $request->location_id)->first();

        $configurationProfileId = $location->wifi_configuration_profile_id;

        $configurationProfile = "";
        if(isset($configurationProfileId)) {
            $configurationProfile = WifiConfigurationProfiles::where('id', $configurationProfileId)->first();

            if($configurationProfile) {
                $configurationProfile = json_decode($configurationProfile->settings, true);
            }
        }

       $essid = null;
       $network_type = null;
       $radius_ip = null;
       $radius_secret = null;
       if (isset($configurationProfile['ssid'])) {
           foreach ($configurationProfile['ssid'] as $ssid) {
               if (isset($ssid['network_type']) && $ssid['network_type'] === 'hotspot-cp' && $ssid['essid'] === $request->ssid) {
                   $essid = $ssid['essid'];
                   $network_type = $ssid['network_type'];
                   $radius_ip = $ssid['radius_ip'];
                   $radius_secret = $ssid['radius_secret'];
                   $login_url = env('CUSTOM_RADIUS_LOGIN_URL') . '?ssid=' . $ssid['essid'];;
                   break;
               }
           }
       }

       if($essid === $request->ssid && $network_type === "hotspot-cp") {
           if ($radius_ip && $radius_secret) {
               $client = new Radius();
               $client->setServer($radius_ip)->setSecret($radius_secret);

               $username = $request->phone;
               $passwordUser = $request->password;
               $authenticated = $client->accessRequest($username, $passwordUser, 5, 6);

               if ($authenticated === false) {

                   return response()->json([
                       'message' => 'Access-Request failed'
                   ], 404);

               } else {
                   $login_url = '';
                   if(! is_null($request->challenge)){
                       if(strlen($request->challenge)> 0){
                           // $password =
                           $wifiuser = DB::connection('mysql2')->table('radcheck')
                               ->where('username', $username)
                               ->first();
                           $password = $request->password;
                           $challenge = $request->challenge;
                           $uamsecret = '';
                           $hexchal = pack ("H32", $challenge);
                           $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
                           $response = md5("\0" .$password . $newchal);
                           $newpwd = pack("a32", $password);
                           $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

                           $login_url = 'http://' . $request->ip .':'. $request->port . '/logon?username='.$username.'&response='.$response.'&userurl=';

                       }
                   }

                   return response()->json([
                       'message' => 'Success',
                       'login_url' =>$login_url
                   ], 200);
               }
           } else {
               return response()->json([
                   'message' => 'Failed'
               ], 200);
           }
       } else {
            return response()->json([
                'message' => 'Failed'
            ], 200);
        }

   }

    /*function radiusAuthenticate() {
        $radius_handle = radius_auth_open();

        if (!$radius_handle) {
            die("Could not create radius handle\n");
        }

        if (!radius_add_server($radius_handle, $radius_server, 1812, $radius_secret, 5, 3)) {
            die("Could not add RADIUS server\n");
        }

        if (!radius_create_request($radius_handle, RADIUS_ACCESS_REQUEST)) {
            die("Could not create RADIUS request\n");
        }

        if (!radius_put_attr($radius_handle, RADIUS_USER_NAME, $username)) {
            die("Could not put username attribute\n");
        }

        if (!radius_put_attr($radius_handle, RADIUS_USER_PASSWORD, $password)) {
            die("Could not put password attribute\n");
        }

        $result = radius_send_request($radius_handle);

        if ($result == RADIUS_ACCESS_ACCEPT) {
            echo "Success! Received Access-Accept response from RADIUS server.\n";
        } else {
            echo "Access-Request failed. ";
            if ($result == RADIUS_ACCESS_REJECT) {
                echo "Access-Reject response received.\n";
            } else {
                echo "Error: " . radius_strerror($radius_handle) . "\n";
            }
        }

        radius_close($radius_handle);
    }*/


    private function processOrder($order, $user)
    {
        //Log::info($order);
        $plan_id = $order->plan_id;
        $plan = InternetPlan::find($plan_id);

        $bandwidth = $plan->bandwidth;
        $session_duration = $plan->session_duration;
        $data_limit = $plan->data_limit;
        $expiration_time = $plan->validity * 60;
        $phone = $user->phone;
        $add_on_type = $order->add_on_type;

        $wifiUser = WiFiUser::where('phone', $phone)->first();
        if (!$wifiUser) {
            $password = Self::generate_password(10);
        } else {
            $password = $wifiUser->password;
        }
        $location = $order->location;
        $session_duration_window = 0;

        if (isset($plan->session_duration_window)) {
            $session_duration_window = $plan->session_duration_window;
        }

        if (!$user) {
            if ($wifiUser) {
                if ($wifiUser->user_id) {
                    $user = User::find($wifiUser->user_id);
                } else if ($order->user_id) {
                    $user = User::find($order->user_id);
                } else {
                    $user = User::where('phone', $phone)->first();
                }
            } else {
                $user = User::where('phone', $phone)->first();
            }
        }
        /*
            if ($location_info && $location_info->owner) {
                $owner = $location_info->owner;
                $payouts = [];
                if ($user && $owner) {
                    $parent_id = '';
                    $acquisition = UserAcquisition::where('user_id', $user->id)->first();

                    if (!$acquisition) {
                        UserAcquisition::create(['user_id' => $user->id, 'owner_id' => $owner->id]);
                        $parent_id = $owner->id;
                    } else {
                        $parent_id = $acquisition->owner_id;
                    }
                }
            }

        */

        /*
        if ($location && $location->owner) {
            $owner = $location->owner;
            $payouts = [];
            $payout = Payout::addCommission($owner, $order);
            $payouts[] = $payout;
            if ($user && $owner) {
                $parent_id = '';
                $acquisition = UserAcquisition::where('user_id', $user->id)->first();

                if (!$acquisition) {
                    UserAcquisition::create(['user_id' => $user->id, 'owner_id' => $owner->id]);
                    $parent_id = $owner->id;
                } else {
                    $parent_id = $acquisition->owner_id;
                }

                $acquisition_payout = Payout::addAcquisitionCommission($order, $parent_id, $user->id);
                if ($acquisition_payout) {
                    $payouts[] = $acquisition_payout;
                }
            }

            // UpdatePayableToZohoBook::dispatch($payouts);
        }
        */

        $radWiFiUser = DB::connection('mysql2')->table('radcheck')->where('username', $phone)->first();
        $radUserGroup = $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();

        //$radiusUserArray = (array) $radWiFiUser;
        $wifiOrders = WiFiOrders::where('phone', $phone)->where('status', '1')->where('data_used', false)->orderBy('data_used', 'ASC')->orderBy('created_at', 'ASC') ->get();

        //Log::info('wifi_orders count -------> '.$wifiOrders);
        //add on data
        $now = time();
        if ($wifiOrders->count() > 1 && $radWiFiUser->expiration_time > $now) {
            // If there are WiFi orders, hold the record and do not update `radcheck` table
            //Log::info('radcheck are not update ');
            return 1;
        } else {

            if (!$radWiFiUser) {
                $expiration_time = time() + $expiration_time;

                DB::connection('mysql2')->table('radcheck')->insert([
                    'username' => $phone,
                    'value' => $password,
                    'attribute' => 'Cleartext-Password',
                    'op' => ':=',
                    'expiration_time' => $expiration_time,
                    'bandwidth' => $bandwidth,
                    'session_duration' => $session_duration,
                    'session_duration_window' => $session_duration_window,
                    'data_limit' => $data_limit,
                    'plan_start_time' => now()
                ]);

            } else {
                $now = time();
                if (($radWiFiUser->expiration_time > $now) && ($plan->data_roll_over == 1) ) {
                    $expiration_time = $radWiFiUser->expiration_time + $expiration_time;
                    $data_limit = $radWiFiUser->data_limit + $data_limit;

                    if (is_null($radWiFiUser->plan_start_time)) {

                        DB::connection('mysql2')->table('radcheck')->where('username', $phone)
                            ->update([
                                'expiration_time' => $expiration_time,
                                'bandwidth' => $bandwidth,
                                'session_duration' => $session_duration,
                                'session_duration_window' => $session_duration_window,
                                'data_limit' => $data_limit,
                                'plan_start_time' => now()
                            ]);
                    } else {
                        $plan_start_time = $radWiFiUser->plan_start_time;
                        DB::connection('mysql2')->table('radcheck')->where('username', $phone)
                            ->update([
                                'expiration_time' => $expiration_time,
                                'bandwidth' => $bandwidth,
                                'session_duration' => $session_duration,
                                'session_duration_window' => $session_duration_window,
                                'data_limit' => $data_limit,
                                'plan_start_time' => $plan_start_time,
                            ]);
                    }

                } else {
                    /*if ($order->add_on_type == 1 && $radWiFiUser) {

                        $add_on_data_limit = $radWiFiUser->data_limit + $data_limit;
                        $add_on_data = $radWiFiUser->add_on_data + $data_limit;
                        DB::connection('mysql2')->table('radcheck')->where('username', $phone)->update([
                            'data_limit' => $add_on_data_limit,
                            'add_on_data' => $add_on_data,
                        ]);
                        Log::info('adding  data in radcheck save success ' .$order->add_on_type);

                    }*/
                    $expiration_time = time() + $expiration_time;
                    // $plan_start_time = $radWiFiUser->plan_start_time;
                    DB::connection('mysql2')
                        ->table('radcheck')
                        ->where('username', $phone)
                        ->update([
                            'expiration_time' => $expiration_time,
                            'bandwidth' => $bandwidth,
                            'session_duration' => $session_duration,
                            'data_limit' => $data_limit,
                            'session_duration_window' => $session_duration_window,
                            'plan_start_time' => now()
                        ]);
                    //Log::info('adding  data in radcheck save success ' .$order->add_on_type);
                }
                /*DB::connection('mysql2')->table('radcheck')->where('username', $phone)
                    ->update([
                        'expiration_time' => $expiration_time,
                        'bandwidth' => $bandwidth,
                        'session_duration' => $session_duration,
                        'data_limit' => $data_limit
                    ]);
                    */
            }
        }
        if ($radUserGroup) {
            $radUserGroup = DB::connection('mysql2')->table('radusergroup')
                ->where('username', $phone)
                ->update(array(
                    'username' => $phone,
                    'groupname' => 'pmwani',
                    'priority' => '100',
                ));
        } else {
            DB::connection('mysql2')->table('radusergroup')->insert([
                'username' => $phone,
                'groupname' => 'pmwani',
                'priority' => '100',
            ]);
        }

        return $plan;
    }

    public function wifiUserAction(Request $request) //suspend, unsuspend
    {
        
        try{

                $id = $request->id;

                $query = User::where('role', 2)
                        ->where("id", $id);

                $user = $query->first();

                if($user){

                if($user->suspend == '1'){
                    $user->suspend = '0';
                }else{
                    $user->suspend = '1';                
                }
                    
                $user->save();
                $command = ($request->cmd == "suspend") ? "Suspended" : "Unsuspended";
                return response()->json(['message' => "$command successfully"], 200);
            }else{
                return response()->json(['message' => "Somethig went wrong"], 203); 
            }
        }
        catch(\Exception $e){
            Log::error('Issue on Suspending User', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went wrong Suspending/Unsuspending User ', 'error' => $e->getMessage()], 500);
        }
    }


}
