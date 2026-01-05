<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WifiOtpController;
use App\Models\LanguageTranslations;
use App\Models\NetworkSettings;
use App\Models\User;
use App\Models\wifiOTP;
use App\Models\URLLogin;
use App\Models\AppLoginLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users,email', 'max:255'],
            'phone' => ['required', 'digits_between:8,12', 'unique:users,phone'],
            'password' => ['required', 'confirmed', 'min:6'],
        ]);

        $data = $request->all();
        $data['first_name'] = $data['name'];
        unset($data['name']);
        unset($data['password_confirmation']);
        $data['password'] = bcrypt($data['password']);
        $data['role'] = config('constants.roles.customer');
        DB::beginTransaction();
        $user = User::create($data);

        $otp = rand(1000, 9999);
        $url_code = Str::random(6);

        $otpRequest = array();
        $otpRequest['phone'] = $user->phone;
        $otpRequest['otp'] = $otp;
        $otpRequest['challenge'] = '';
        $otpRequest['url_code'] = $url_code;
        $otpRequest['mac_address'] = '';
        $generatedOTP = wifiOTP::create($otpRequest);
        $os = $request->os;
        $send_otp = WifiOtpController::send_sms($otp, $url_code, $user->phone, $os);
        DB::commit();

        $network_settings = NetworkSettings::first();
        $free_download = $network_settings->free_download;
        $radius_wifiuser = DB::connection('mysql2')->table('radcheck')->where('username', $request->mac_address)->first();

        $now = time();

            if (!$radius_wifiuser) {

                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                    ->insert([
                        'username' => $request->mac_address ?? $request->phone,
                        'value' => $request->mac_address ?? $request->phone,
                        'attribute' => 'Cleartext-Password',
                        'op' => ':=',
                        'data_limit' => '-1',
                        'session_duration' => '-5',
                        'bandwidth' => '20480',
                        'expiration_time' => $now,
                        'phone_number' => $user->phone,
                        'name' => $user->first_name.' '.$user->last_name
                    ]);

            } else {
                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                    ->where('username', $request->phone ?? $request->mac_address)
                    ->update([
                        'phone_number' => $user->phone,
                        'name' => $user->first_name.' '.$user->last_name,
                        'data_limit' => '-1',
                        'session_duration' => '-5',
                        'bandwidth' => '20480',
                        'expiration_time' => $now,
                    ]);
            }

        // Todo: remove otp from response
        return compact('user', 'url_code', 'otp');
    }

    public function test(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'digits_between:8,12'],
        ]);

        $user = User::where('email', $request->email)->orWhere('phone', $request->phone)->first();
        $token = Auth::login($user);

        // Todo: remove otp from response
        return compact('user', 'token');
    }

    public function url_login(Request $request)
    {
        $request->validate([
            'url_code' => ['required', 'string', 'max:6']
        ]);

        $user_info = URLLogin::where('url_code',$request->url_code)->first();
        if($user_info){
            $user = User::where('id', $user_info->user_id)->first();
            $token = Auth::login($user);

            // Todo: remove otp from response
            // return compact('user', 'token');
            return response()->json([
                'status' => 'success',
                'message' => 'WiFi user Verified',
                'token' => $token,
                'user' => $user
                //'request' => $request->all()
            ], 201);
        }else{
            return response()->json([
                'status' => 'failure',
                'message' => 'Code did not validate',
            ], 201);
        }
    }

    public function direct_login($userId)
    {
        /*$request->validate([
            'id' => ['required', 'integer']
        ]);
        */
        $user = Auth::user();
        // return $user;
        if($user->role == 0){
            $user_info = URLLogin::where('url_code',$userId)->first();
            $code = Str::random(6);
            if(! $user_info){
                // Add user code here
                URLLogin::create(['user_id'=>$userId, 'url_code' => $code]);
            }else{
                $code = $user_info->url_code;
            }

                // Todo: remove otp from response
                // return compact('user', 'token');
                return response()->json([
                    'status' => 'success',
                    'message' => 'WiFi user Verified',
                    'code' => $code
                ], 201);
            }else{
                return response()->json([
                    'status' => 'failure',
                    'message' => 'Code did not validate',
                ], 201);
            }

    }

    public function register_pmwwani_user(Request $request)
	{
		$pmwani_user_request = $request->all();
		//return $pmwani_user_request;
	    $userData = [
            'phone' => $pmwani_user_request['Username'],
            // 'username' => $pmwani_user_request['Username'],
            'password' => bcrypt($pmwani_user_request['password']),
            'first_name' => ''
        ];
//		return $userData;
		$user = User::where('phone', $userData['phone'])->first();
		if(! $user){
			$user = User::create($userData);
			$user->role = 2;
			$user->save();
		}else{
			$user->phone = $userData['phone'];
			$user->password = $userData['password'];
			$user->role = 2;
			$user->first_name = $userData['first_name'];
			$user->save();
		}

        $app_log_data = array();
        $app_log_data['username'] = $pmwani_user_request['Username'];
        $app_log_data['app_id'] = $pmwani_user_request['app_id'];

		$app_log = AppLoginLog::create($app_log_data);

        // Todo: remove otp from response
        $token = Auth::login($user);
        return compact('user', 'token');
    }

    public function setLanguage(Request $request)
    {
        $userId = Auth::user()->id;

        $user = User::where('id', $userId)->first();
        $user->preffered_language = $request->language;
        $user->save();

        return response()->json([
            'message' => 'language updated successfully',
            'language' => $user->preffered_language,
        ], 200);

    }


    public function getLanguage(Request $request)
    {
        if ($request->language) {

            $language = LanguageTranslations::where('code', $request->language)->first();
            return response()->json([
                'message' => 'language fetched successfully',
                'language' => $request->language,
                'json' => $language->translation,
            ], 200);

        } else {
            $userId = Auth::user()->id;
            $user = User::where('id', $userId)->first();

            $language = LanguageTranslations::where('code', $user->preffered_language)->first();

            return response()->json([
                'message' => 'language fetched successfully',
                'language' => $user->preffered_language,
                'json' => $language->translation,
            ], 200);
        }
    }
}
