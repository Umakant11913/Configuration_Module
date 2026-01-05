<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\PdoaPlan;
use App\Models\PdoSmsQuota;
use App\Models\SmsHistory;
use App\Models\User;
use App\Models\wifiOTP;
use App\Models\NetworkSettings;
use App\Models\WiFiUser;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use DateTime;
use DB;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Validator;

class WifiOtpController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => ['create', 'verify', 'generate_login', 'login', 'verify_url', 'extend_free', 'verify_otp_status', 'verifyOtp', 'resendOtp', 'resend']
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function verify_otp_status(Request $request)
    {
        $mac = $request->mac;
        $otp_record = wifiOTP::where('mac_address', $mac)->whereBetween('created_at', [now()->subMinutes(10), now()])->first();
        if ($otp_record) {
            return response()->json([
                'status' => true,
                'message' => 'WiFi OTP was sent recently',
                'request' => $request->all(),
                'data' => $otp_record->phone
            ], 201);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'WiFi OTP was NOT sent recently',
                'request' => $request->all()
            ], 201);
        }

    }

    public function extend_free($phone, Request $request)
    {

        // $phone = $request->phone;
        $location_id = $request->location;
        // $challenge = $request->challenge;
        $mac = $request->mac;

        $date = new DateTime;
        $today = date('d-m-Y');
        $yesterday = date("Y-m-d", strtotime("-1 day"));

        $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->where('username', $mac)->first();
        $session_info = DB::connection('mysql2')->table('radacct')
            ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
            ->where('username', $mac)
            ->where('start_date', '>', $yesterday)
            ->get();

        $total_download = $session_info[0]->downloads + $session_info[0]->uploads;
        $available_free_downloads = DB::connection('mysql2')->table('free_session')
            ->select(DB::raw('IFNULL(free_download,0) as free_download, IFNULL(extension_download,0) as extension_download'))
            ->get();
        if (!isset($radius_wifiuser->extension_date)) {
            $extension_date = '';
        } else {
            $extension_date = $radius_wifiuser->extension_date;
        }

        $max_available_download = $available_free_downloads[0]->free_download + $available_free_downloads[0]->extension_download;

        if (($extension_date != $today) || ($total_download < $max_available_download)) {
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                ->where('username', $mac)
                ->where('extension_date', $today)
                ->update([
                    'extension_date' => $today
                ]);

            // $phone = $request->phone;
            $otp = $this->generate_otp(4);
            $url_code = $this->generate_password(6);
            $os = $request->os;

            $otpRequest = array();
            $otpRequest['phone'] = $phone;
            $otpRequest['otp'] = $otp;
            $otpRequest['challenge'] = $request->challenge;
            $otpRequest['url_code'] = $url_code;
            $otpRequest['mac_address'] = $request->mac;
            $generatedOTP = wifiOTP::create($otpRequest);
            // $send_otp = Self::send_otp('91'.$phone, $otp, $os);
            $send_otp = self::send_sms($otp, $url_code, $phone, $os);
            // $otp, $url_code, $phone, $os

            return response()->json([
                'status' => true,
                'message' => 'OTP Sent Successfully',
                'otp' => $otp,
                'url' => $url_code,
                'request' => $request->all()
            ], 201);
            /*
            $username = $password = $request->mac;
            $challenge = $request->challenge;
            $uamsecret = '';
            $hexchal = pack ("H32", $challenge);
            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
            $response = md5("\0" .$password . $newchal);
            $newpwd = pack("a32", $password);
            $pappassword = implode ('', unpack("H32", ($newpwd ^ $newchal)));

            $data = array();

            $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username='.$username.'&response='.$response.'&userurl=';
            */

            return response()->json([
                'status' => true,
                'message' => 'WiFi Extended',
                'request' => $request->all()
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Extended Already',
                'request' => $request->all()
            ], 201);
        }
    }

    public function verify_url(Request $request)
    {

        $phone = $request->phone;
        $url_code = $request->url_code;
        $mac_address = $request->mac_address;
        $date = new DateTime;
        $date->modify('-5 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');
        $verifyOTP = wifiOTP::where('url_code', $url_code)->first();

        if ($verifyOTP) {
            $phone = $verifyOTP->phone;
            $mac_address = $verifyOTP->mac_address;

            $verifyOTP->status = 1;
            $verifyOTP->save();
            $wifiUser = WiFiUser::where('phone', $phone)->where('mac_address', $mac_address)->first();

            if (!$wifiUser) {
                $wifi_user = array();
                $wifi_user['phone'] = $phone;
                $wifi_user['mac_address'] = $mac_address;
                $wifi_user['password'] = Self::generate_password(10);
                $wifiUser = WiFiUser::create($wifi_user);
            }

            $user = User::where('phone', $phone)->first();
            if (!$user) {
                $user = User::create(['phone' => $phone, 'role' => config('constants.roles.customer')]);
            }

            $user->otp_verified_on = Carbon::now();
            $user->save();

            $wifiUser->user_id = $user->id;
            $wifiUser->save();

            $token = Auth::login($user);

            $network_settings = NetworkSettings::first();
            $free_download = $network_settings->free_download;
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->where('username', $mac_address)->first();

            $now = time();

            if (!$radius_wifiuser) {

                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->insert([
                    'username' => $mac_address,
                    'value' => $mac_address,
                    'attribute' => 'Cleartext-Password',
                    'op' => ':=',
                    'data_limit' => $free_download,
                    'expiration_time' => $now
                ]);
            } else {
                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                    ->where('username', $mac_address)
                    ->update([
                        'data_limit' => $free_download
                    ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'OTP Verified Successfully',
                'wifiUser' => $wifiUser,
                'data' => $verifyOTP,
                'request' => $request->all(),
                'user' => $user,
                'token' => $token
            ], 201);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'OTP Verification failed',

                'request' => $request->all()
            ], 201);
        }
    }

    public function resendOtp(Request $request)
    {
        $phone = $request->phone;
        $urlCode = $request->url_code;

        $verifyOTP = wifiOTP::where('phone', $phone)
            ->where('url_code', $urlCode)
            ->first();

        if (!$verifyOTP) {
            return response()->json([
                'status' => false,
                'message' => 'OTP Resend failed.',
                'request' => $request->all()
            ], 422);
        }

        $send_otp = self::send_sms($verifyOTP->otp, $urlCode, $phone, '');

        return response()->json([
            'status' => true,
            'message' => 'OTP Sent Successfully',
        ], 201);
    }

    public function verifyOtp(Request $request)
    {
        $phone = $request->phone;
        $otp = $request->otp;
        $urlCode = $request->url_code;

        $date = new DateTime;
        $date->modify('-15 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');
        $verifyOTP = wifiOTP::where('phone', $phone)
            ->where('url_code', $urlCode)
            ->where('otp', $otp)
            ->where('updated_at', '>=', $formatted_date)
            ->first();

        if (!$verifyOTP) {
            return response()->json([
                'status' => false,
                'message' => 'OTP Verification failed',
                'request' => $request->all()
            ], 422);
        }

        $verifyOTP->status = 1;
        $verifyOTP->save();

        $user = User::where('phone', $phone)->first();

        $user->otp_verified_on = Carbon::now();
        $user->save();

        $token = Auth::login($user);

        return response()->json([
            'status' => true,
            'message' => 'OTP Verified Successfully',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function verify(Request $request)
    {

        $phone = $request->phone;
        $otp = $request->otp;
        $mac_address = $request->mac_address;
        $date = new DateTime;
        $date->modify('-5 minutes');
        $formatted_date = $date->format('Y-m-d H:i:s');
        $verifyOTP = wifiOTP::where('phone', $phone)->where('mac_address', $mac_address)->where('otp', $otp)->where('updated_at', '>=', $formatted_date)->first();

        if ($verifyOTP) {

            $verifyOTP->status = 1;
            $verifyOTP->save();
            $wifiUser = WiFiUser::where('phone', $phone)->where('mac_address', $mac_address)->first();

            if (!$wifiUser) {

                $wifi_user = array();
                $wifi_user['phone'] = $phone;
                $wifi_user['mac_address'] = $mac_address;
                $wifi_user['password'] = Self::generate_password(10);
                $wifiUser = WiFiUser::create($wifi_user);
            }

            $user = User::where('phone', $phone)->first();

            $user->otp_verified_on = Carbon::now();
            $user->save();

            $wifiUser->user_id = $user->id;
            $wifiUser->save();

            if (!$user->password) {
                $token = Password::getRepository()->create($user);
                $user->sendPasswordResetNotification($token);
            }

            $token = Auth::login($user);

            $network_settings = NetworkSettings::first();
            $free_download = $network_settings->free_download;
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->where('username', $request->mac_address)->first();

            $now = time();

//            if (!$radius_wifiuser) {
//
//                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
//                    ->insert([
//                        'username' => $request->phone ?? $request->mac_address,
//                        'value' => $request->phone ?? $request->mac_address,
//                        'attribute' => 'Cleartext-Password',
//                        'op' => ':=',
//                        'data_limit' => $free_download,
//                        'expiration_time' => $now,
//                        'phone_number' => $user->phone,
//                        'name' => $user->first_name.' '.$user->last_name
//                    ]);
//
//            } else {
//                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
//                    ->where('username', $request->phone ?? $request->mac_address)
//                    ->update([
//                        'data_limit' => $free_download,
//                        'phone_number' => $user->phone,
//                        'name' => $user->first_name.' '.$user->last_name
//                    ]);
//            }

            return response()->json([
                'status' => true,
                'message' => 'OTP Verified Successfully1',
                'wifiUser' => $wifiUser,
                'verifyOTP' => $verifyOTP,
                'token' => $token,
                'request' => $request->all()
            ], 201);

        } else {
            return response()->json([
                'status' => false,
                'message' => 'OTP Verification failed',
                'request' => $request->all()
            ], 201);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function create(Request $request)
    {
        //return $request;
        $request->validate([
           /* 'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],*/
            'phone' => ['required', 'digits_between:8,12'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);
        $data = $request->all();
        $data['first_name'] = $data['name'] ?? null;
        unset($data['name']);
        unset($data['password_confirmation']);
        $data['app_password'] = $data['password'];
        $data['password'] = bcrypt($data['password']);

        $phone = $request->phone;
        $otp = $this->generate_otp(4);
        $url_code = $this->generate_password(6);
        $os = $request->os;

        $phone = $data['phone'];
        $user = User::where('phone', $phone)->first();

        if ($user) {
            $user->update([
                'first_name' => $data['first_name'] ?? null,
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'app_password' => $data['app_password'],
                'phone' => $data['phone'],
                'role' => 2,
                'location_id' => $request->locationId ?? 0,
                'preffered_language' => 'en'

            ]);
        } else {
            $user = User::create([
                'first_name' => $data['first_name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'app_password' => $data['app_password'],
                'phone' => $data['phone'],
                'role' => 2,
                'location_id' => $request->locationId ?? 0,
                'preffered_language' => 'en'
            ]);
        }

        $otpRequest = array();
        $otpRequest['phone'] = $phone;
        $otpRequest['otp'] = $otp;
        $otpRequest['challenge'] = $request->challenge ?? null;
        $otpRequest['url_code'] = $url_code;
        $otpRequest['mac_address'] = $request->macAddress ?? null;
        /* $generatedOTP = wifiOTP::create($otpRequest);*/

        $network_settings = NetworkSettings::first();
        $free_download = $network_settings->free_download;
        $radius_wifiuser = \Illuminate\Support\Facades\DB::connection('mysql2')->table('radcheck')->where('username', $request->macAddress ?? $phone)->first();
        $now = time();

        //if location id not null or 0
        if ($request->locationId !== null && $request->locationId !== '0') {
            $location = Location::where('id', $request->locationId)->first(); //find location
            $pdoUser = User::where('id', $location->owner_id)->first(); //find PDO user
            $pdoPlan = PdoaPlan::where('id', $pdoUser->pdo_type)->first(); //find PDO's plan

            $smsQuotaRecordID = null;

            //if PDO's plan has details with the SUbscription plan
            if ($pdoPlan->service_fee_per_device > 0 && $pdoPlan->validity_period > 0 && $pdoPlan->sms_quota > 0) {

                //find the latest record which gets renewed every 1st of month
                $sms_credits = PdoSmsQuota::where('pdo_id', $location->owner_id)
                    ->where('type', null)->orderBy('created_at', 'desc')->first();
                $balancesms_credits = $sms_credits->sms_quota - $sms_credits->sms_used; //find the SMS balance

                //et list of all SMS add-on or defaults
                /*$addOnSms = PdoSmsQuota::where('pdo_id', $location->owner_id)
                    ->where('type', 'add-on')->orWhere('type', 'default')
                    ->whereRaw('sms_quota - sms_used > 0')
                    ->get();*/
                $addOnSms = PdoSmsQuota::select('*')
                    ->selectRaw('(sms_quota - sms_used) AS balance')
                    ->where('pdo_id', $location->owner_id)
                    ->whereIn('type', ['add-on', 'default'])
                    ->whereRaw('(sms_quota - sms_used) > 0')
                    ->get();
                $oldestRecordWithBalance = null;
                $oldestRecordCreatedAt = null;

                //if renewed SMS balance IGT 0
                if ($balancesms_credits > 0) {
                    $smsQuotaRecordID = $sms_credits->id;
                } else if ($addOnSms->isNotEmpty()) { // if list of SMS add-on or default is not null

                    foreach ($addOnSms as $adOn) {
                        $balance = $adOn->sms_quota - $adOn->sms_used;

                        //if add on or default SMS found
                        if ($balance > 0 && ($oldestRecordWithBalance == null || $adOn->created_at < $oldestRecordCreatedAt)) {
                            $oldestRecordWithBalance = $adOn;
                            $oldestRecordCreatedAt = $adOn->created_at;

                            $smsQuotaRecordID = $oldestRecordWithBalance->id;

                        }
                    }
                } else { //if no SMS balance is 0
                    return response()->json([
                        'status' => false,
                        'message' => 'OTP cannot be Sent!',
                        'sms_balance' => '0',
                        'pdo' => $pdoUser,
                        'otp' => $otp,
                        'url' => $url_code,
                        'user' => $user,
                        'request' => $request->all()
                    ], 201);
                }

                //if any record found which has pending SMS balance
                if ($smsQuotaRecordID !== null) {
                    $generatedOTP = wifiOTP::create($otpRequest);
                    $send_otp = self::send_sms($otp, $url_code, $phone, $os);

                    $this->smsHistory($user, $location->owner_id, $request, $smsQuotaRecordID);

                    $pdoSmsQuota = PdoSmsQuota::where('id', $smsQuotaRecordID)->first();
                    $pdoSmsQuota->sms_used = $pdoSmsQuota->sms_used + 1;
                    $pdoSmsQuota->save();

                    if (!$radius_wifiuser) {

                        $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                            ->insert([
                                'username' => $request->phone ?? $request->macAddress,
                                'value' => $request->phone ?? $request->macAddress,
                                'attribute' => 'Cleartext-Password',
                                'op' => ':=',
                                'data_limit' => '-1',
                                'session_duration' => '5',
                                'bandwidth' => '20480',
                                'expiration_time' => $now,
                                'phone_number' => $user->phone,
                                'name' => $user->first_name ?? null . ' ' . $user->last_name ?? null
                            ]);
                        $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();
                        if (!$radUserGroup) {
                            DB::connection('mysql2')->table('radusergroup')->insert([
                                'username' => $phone,
                                'groupname' => 'pmwani',
                                'priority' => '100',
                            ]);
                        }

                    } else {
                        $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                            ->where('username', $request->macAddress ?? $request->phone)
                            ->update([
                                'username' => $request->phone ?? $request->macAddress,
                                'phone_number' => $user->phone,
                                'name' => $user->first_name ?? null. ' ' . $user->last_name ?? null,
                                'data_limit' => '-1',
                                'session_duration' => '5',
                                'bandwidth' => '20480',
                                'expiration_time' => $now,
                            ]);
                        $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();
                        if (!$radUserGroup) {
                            DB::connection('mysql2')->table('radusergroup')->insert([
                                'username' => $phone,
                                'groupname' => 'pmwani',
                                'priority' => '100',
                            ]);
                        }
                    }

                }
            } else {
                $generatedOTP = wifiOTP::create($otpRequest);
                $send_otp = self::send_sms($otp, $url_code, $phone, $os);
                if (!$radius_wifiuser) {

                    $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                        ->insert([
                            'username' => $request->phone ?? $request->macAddress,
                            'value' => $request->phone ?? $request->macAddress,
                            'attribute' => 'Cleartext-Password',
                            'op' => ':=',
                            'data_limit' => '-1',
                            'session_duration' => '5',
                            'bandwidth' => '20480',
                            'expiration_time' => $now,
                            'phone_number' => $user->phone,
                            'name' => $user->first_name ?? null . ' ' . $user->last_name ?? null
                        ]);
                    $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();
                    if (!$radUserGroup) {
                        DB::connection('mysql2')->table('radusergroup')->insert([
                            'username' => $phone,
                            'groupname' => 'pmwani',
                            'priority' => '100',
                        ]);
                    }

                } else {
                    $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                        ->where('username', $request->macAddress ?? $request->phone)
                        ->update([
                            'username' => $request->phone ?? $request->macAddress,
                            'phone_number' => $user->phone,
                            'name' => $user->first_name ?? null . ' ' . $user->last_name ?? null,
                            'data_limit' => '-1',
                            'session_duration' => '5',
                            'bandwidth' => '20480',
                            'expiration_time' => $now,
                        ]);
                    $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();
                    if (!$radUserGroup) {
                        DB::connection('mysql2')->table('radusergroup')->insert([
                            'username' => $phone,
                            'groupname' => 'pmwani',
                            'priority' => '100',
                        ]);
                    }
                }
            }

        } else {
            $generatedOTP = wifiOTP::create($otpRequest);
            $send_otp = self::send_sms($otp, $url_code, $phone, $os);

            if (!$radius_wifiuser) {

                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                    ->insert([
                        'username' => $request->phone ?? $request->macAddress,
                        'value' => $request->phone ?? $request->macAddress,
                        'attribute' => 'Cleartext-Password',
                        'op' => ':=',
                        'data_limit' => '-1',
                        'session_duration' => '5',
                        'bandwidth' => '20480',
                        'expiration_time' => $now,
                        'phone_number' => $user->phone,
                        'name' => $user->first_name ?? null . ' ' . $user->last_name ?? null
                    ]);
                $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();
                if (!$radUserGroup) {
                    DB::connection('mysql2')->table('radusergroup')->insert([
                        'username' => $phone,
                        'groupname' => 'pmwani',
                        'priority' => '100',
                    ]);
                }

            } else {
                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                    ->where('username', $request->macAddress ?? $request->phone)
                    ->update([
                        'username' => $request->phone ?? $request->macAddress,
                        'phone_number' => $user->phone,
                        'name' => $user->first_name ?? null. ' ' . $user->last_name ?? null,
                        'data_limit' => '-1',
                        'session_duration' => '5',
                        'bandwidth' => '20480',
                        'expiration_time' => $now,
                    ]);
                $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $phone)->first();
                if (!$radUserGroup) {
                    DB::connection('mysql2')->table('radusergroup')->insert([
                        'username' => $phone,
                        'groupname' => 'pmwani',
                        'priority' => '100',
                    ]);
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP Sent Successfully!',
            'otp' => $otp,
            'url' => $url_code,
            'user' => $user,
            'request' => $request->all()
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    private function smsHistory($user, $pdoUser, $data, $smsQuotaId)
    {
        $data = [
            'pdo_id' => $pdoUser,
            'quota_id' => $smsQuotaId,
            'user_id' => $user->id,
            'phone' => $user->phone
        ];

        $smsHistory = SmsHistory::create($data);

        if ($smsHistory) {
            return 1;
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Not created',
            ], 201);
        }
    }

    public function store(Request $request)
    {
        //
    }

    public function generate_login(Request $request)
    {
        $username = $password = $request->phone ?? $request->mac_address;
        $challenge = $request->challenge;
        $uamsecret = '';
        $hexchal = !empty($challenge) ? pack("H32", $challenge) : '';
        $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
        $response = md5("\0" . $password . $newchal);
        $newpwd = pack("a32", $password);
        $pappassword = !empty($challenge) ? implode('', unpack("H32", ($newpwd ^ $newchal))) : '';

        $data = array();

        /*if($username !== null || $request->challenge !== null){
            $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username=' . $username.'--Free' . '&response=' . $response . '&userurl=';
        } else {
            $data['login_redirect_url'] = env('FRONTEND_URL') . '/login';
        }*/

        $data['login_redirect_url'] = env('FRONTEND_URL') . '/login';

        //$data['response'] = $response;
        //$data['wifiUsage']= $wifiUsageToday;
        // $data['username'] = $username;

        return response()->json([
            'success' => true,
            'message' => "WiFi Login Successful",
            "data" => $data,
            "request" => $request->all()
        ], 200);
    }

    public function resend(Request $request)
    {
        $phone = $request->phone;
        $urlCode = $request->url_code;

        $verifyOTP = wifiOTP::where('phone', $phone)
            ->first();

        if (!$verifyOTP) {
            return response()->json([
                'status' => false,
                'message' => 'OTP Resend failed.',
                'request' => $request->all()
            ], 422);
        }

        $verifyOTP->updated_at = Carbon::now();
        $verifyOTP->save();

        $send_otp = self::send_sms($verifyOTP->otp, $urlCode, $phone, '');
        $user = User::where('phone', $phone)->first();

        return response()->json([
            'status' => true,
            'message' => 'OTP Sent Successfully',
        ], 201);
    }

    public function dologin(Request $request)
    {
        $username = $request->phone;

        $wifiuser = WiFiUser::where('phone', $phone)->first();

        $challenge = $request->challenge;
        $uamsecret = '';
        $hexchal = pack("H32", $challenge);
        $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
        $response = md5("\0" . $password . $newchal);
        $newpwd = pack("a32", $password);
        $pappassword = implode('', unpack("H32", ($newpwd ^ $newchal)));

        $data = array();

        $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username=' . $username . '&response=' . $response . '&userurl=';
        //$data['response'] = $response;
        //$data['wifiUsage']= $wifiUsageToday;
        // $data['username'] = $username;

        return response()->json([
            'success' => true,
            'message' => "WiFi Login Successful",
            "data" => $data,
            "request" => $request->all()
        ], 200);
    }

    public function doAllLogin(Request $request)
    {
        $username = $request->username;

        $wifiuser = WiFiUser::where('phone', $username)->first();
        if ($wifiuser) {

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
        //$data['response'] = $response;
        //$data['wifiUsage']= $wifiUsageToday;
        // $data['username'] = $username;

        return response()->json([
            'success' => true,
            'message' => "WiFi Login Successful",
            "data" => $data,
            "request" => $request->all()
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\wifi_otp $wifi_otp
     * @return \Illuminate\Http\Response
     */
    public function show(wifi_otp $wifi_otp)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\wifi_otp $wifi_otp
     * @return \Illuminate\Http\Response
     */
    public function edit(wifi_otp $wifi_otp)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\wifi_otp $wifi_otp
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, wifi_otp $wifi_otp)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\wifi_otp $wifi_otp
     * @return \Illuminate\Http\Response
     */
    public function destroy(wifi_otp $wifi_otp)
    {
        //
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:wi_fi_users,mac_address',
            'challenge' => 'required|string|min:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'data' => null
            ], 400);
            // return response()->json($validator->errors()->toJson(), 400);
        }

        //$network_group = network_groups::findOrFail($request->network_group);
        //$network_group_name = $network_group['name'];

        // $global_values = global_values::findOrFail('1');
        $paid_active = 0;
        $uamsecret = '';//$global_values->uam_secret;
        $wifidevice = WiFiUser::where('mac_address', $request->username)->first();
        $username = $password = '';
        $now = time();
        if (!$wifidevice) {
            return response()->json([
                'success' => false,
                'message' => "WiFi Login Failed",
                "request" => $request
            ], 200);
        } else {

            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->where('username', $wifidevice->phone)->where('expiration_time', '>', $now)->first();

            $now = time();
            $today = date("Y-m-d", strtotime("-1 day"));

            if (!$radius_wifiuser) {

                $radius_wifi_usage = DB::connection('mysql2')->table('radcheck')->where('username', $request->username)->first();

                $session_info = DB::connection('mysql2')->table('radacct')
                    ->select(DB::raw('IFNULL(ceil(sum(acctinputoctets/(1024*1024))),0) as downloads, IFNULL(ceil(sum(acctoutputoctets/(1024*1024))),0) as uploads'))
                    ->where('username', $request->username)
                    ->where('start_date', '>', $today)
                    ->get();

                $available_free_downloads = DB::connection('mysql2')->table('free_session')
                    ->select(DB::raw('IFNULL(free_download,0) as free_download, IFNULL(extension_download,0) as extension_download'))
                    ->get();

                if (!isset($radius_wifiuser->extension_date)) {
                    $extension_date = '';
                } else {
                    $extension_date = $radius_wifiuser->extension_date;
                }

                $max_available_download = $available_free_downloads[0]->free_download + $available_free_downloads[0]->extension_download;

                $total_download = $session_info[0]->downloads + $session_info[0]->uploads;

                if ($total_download >= $max_available_download) {
                    // if ($total_download >= 0) {
                    return response()->json([
                        'success' => true,
                        'message' => "WiFi Session Expired",
                        "data" => $wifidevice
                    ], 200);
                } else {

                    if ($total_download >= $available_free_downloads[0]->free_download) {
                        return response()->json([
                            'success' => true,
                            'message' => "WiFi Session Extension possible",
                            "data" => $wifidevice
                        ], 200);
                    } else {
                        $username = $request->username;
                        $password = $request->username;
                    }
                }
            } else {
                $username = $radius_wifiuser->username;
                $password = $radius_wifiuser->value;

            }
            //$username = $request->username;
            //$password = $request->username;

            //$username = $request->username;
            //$password = $request->username;
            $challenge = $request->challenge;

            $hexchal = pack("H32", $challenge);
            $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
            $response = md5("\0" . $password . $newchal);
            $newpwd = pack("a32", $password);
            $pappassword = implode('', unpack("H32", ($newpwd ^ $newchal)));

            $data = array();

            $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username=' . $username . '&response=' . $response . '&userurl=';
            $data['response'] = $response;
            // $data['username'] = $username;
            if ($paid_active == 0) {
                return response()->json([
                    'success' => true,
                    'message' => "WiFi Login Not available",

                    "request" => $request,
                    "username" => $username,
                    "password" => $password
                ], 200);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => "WiFi Login Successful",
                    "data" => $data,
                    "request" => $request,
                    "username" => $username,
                    "password" => $password
                ], 200);
            }
        }
    }

    public static function send_sms($otp, $url_code, $phone, $os)
    {
        // SMS Sending code goes here
        $phone = '91' . $phone;

        if ($os != 'iOS') {

            //$url_code = env('FRONTEND_URL') . '/a/' . $url_code;
            $url_code = env('FRONTEND_URL');
            $flow_id = '62974583bd6f912e9902dbc9';
            $sender_id = 'INTPLW';
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.msg91.com/api/v5/flow/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "{\n  \"flow_id\": \"$flow_id\",\n  \"sender\": \"$sender_id\",\n  \"mobiles\": \"$phone\",\n  \"otp\": \"$otp\",\n  \"url\": \"$url_code\"\n    \n}",

                CURLOPT_HTTPHEADER => [
                    "authkey: 375442AnKUjTl5f624ed867P1",
                    "content-type: application/JSON"
                ],
            ]);
        } else {
            $flow_id = '6297453c1fc274634c3590e8';
            $sender_id = 'INTPLW';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.msg91.com/api/v5/flow/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "{\n  \"flow_id\": \"$flow_id\",\n  \"sender\": \"$sender_id\",\n  \"mobiles\": \"$phone\",\n  \"otp\": \"$otp\"\n    \n}",

                CURLOPT_HTTPHEADER => [
                    "authkey: 375442AnKUjTl5f624ed867P1",
                    "content-type: application/JSON"
                ],
            ]);
        }


        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return $response;
        }

    }

    /*public function send_otp($phone, $otp, $os)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.msg91.com/api/v5/flow/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\n  \"flow_id\": \"622726c014074f713b220d25\",\n  \"sender\": \"PLSNET\",\n  \"mobiles\": \"$phone\",\n  \"otp\": \"$otp\"\n    \n}",
            CURLOPT_HTTPHEADER => [
                "authkey: 113856AcmCCQZro6219f042P1",
                "content-type: application/JSON"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            return 1;
        }
    }
    */

    public function generate_otp($length = 4)
    {
        $characters = '0123456789';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function generate_password($length = 4)
    {
        $characters = 'abcdefghijklmnopqrstuvwxy9876543210';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }
}
