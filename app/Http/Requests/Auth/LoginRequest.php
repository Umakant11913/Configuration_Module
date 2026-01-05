<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Models\wifiOTP;
use Carbon\Carbon;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lunaweb\RecaptchaV3\RecaptchaV3;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
	$rules = [
            'email' => ['string'],
            'phone' => ['string'],
            'password' => ['required', 'string']
        ];

        return $rules;
    }

    public function authenticate($customer)
    {
        $this->ensureIsNotRateLimited();
        //Log::info('ID::' . $this->email . "Pass::" . $this->pass);

        $credentials = $this->only('password');
         // For Updating Last Login time for Customers
        $user = User::where('phone', $this->phone)->where('role', 2)->first();

        if($customer == "customer" && $user->otp_verified_on === null){
            $status = false;
            return compact('status', 'user');
        } else {
            if($this->email) { $credentials['email'] = $this->email; }
            else if($this->phone) { $credentials['phone'] =  $this->phone; }
            //Log::channel('customlog')->info($credentials);
            $token = Auth::attempt($credentials);
            //Log::channel('customlog')->info($token);
            if (!$token) {
                RateLimiter::hit($this->throttleKey());
                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }


            RateLimiter::clear($this->throttleKey());
            $user = Auth::user();

            if (!$customer && $user->role != config('constants.roles.customer')) {
                $recaptchaResponse = $this->recaptchaResponse;
                $action = 'your-action'; // Replace with the actual ReCAPTCHA action you used in the frontend
                $recaptcha = app(RecaptchaV3::class);

                $validationResult = $recaptcha->verify($recaptchaResponse);

                if (!$validationResult) {
                    return response()->json(['error' => 'Please try again. ReCAPTCHA validation failed! ']);
                }
            }

            if (!$customer && $user->role == config('constants.roles.customer')) {
                abort(403, 'Sorry! You are not allowed to login.');
            }
            if(!empty($this->challenge)) {
                $challenge = wifiOTP::where('phone', $this->phone)->first();

                if (empty($challenge))  {
                    $challenge = new wifiOTP();
                    $challenge->phone = $this->phone;
                    $challenge->challenge = $this->challenge;
                    $challenge->otp = 0;
                    $challenge->url_code = 'null';
                    $challenge->save();
                }
                if ($challenge->challenge) {
                    if ($challenge->challenge !== $this->challenge) {
                        $challenge->challenge = $this->challenge;
                        $challenge->save();
                    }
                } else {
                    $challenge->challenge = $this->challenge;
                    $challenge->save();
                }
            }
            if($user->role == config('constants.roles.customer')){
                $user = User::where('phone', $user->phone)->orWhere('email', $user->email)->first();
                if($user){
                    $user->login_at = $user->last_login_at;
                    $user->last_login_at = Carbon::now();
                    $user->save();
                }
            } else {
                $user = User::where('email', $user->email)->first();
                if($user){
                    $user->login_at = $user->last_login_at;
                    $user->last_login_at = Carbon::now();
                    $user->save();
                }
            }

            return compact('token', 'user');
        }
    }

    public function authenticate_as_pdo_owner()
    {
        /*
        $this->ensureIsNotRateLimited();

        $token = Auth::attempt($this->only('email', 'password'));

        if (!$token) {
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }


        RateLimiter::clear($this->throttleKey());
        $user = Auth::user();
        Log::info($customer);
        Log::info($user->role == config('constants.roles.customer') ? 'TRUE' : 'false');
        if (!$customer && $user->role == config('constants.roles.customer')) {
            abort(403, 'Sorry! You are not allowed to login.');
        }
        return compact('token', 'user');
        */
        return $this;
    }




    public function ensureIsNotRateLimited()
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey()
    {
        return Str::lower($this->input('email')) . '|' . $this->ip();
    }
}
