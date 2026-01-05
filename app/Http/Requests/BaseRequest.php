<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BaseRequest extends FormRequest
{
    protected array $paramsToPrepare = [];

    protected function prepareForValidation()
    {
        $data = $this->all();

        foreach ($this->paramsToPrepare as $key => $type) {
            $value = $this->processInput($key, $type);

            $data = data_set($data, $key, $value);
        }

        $this->merge($data);

        $this->setup();
    }

    protected function setup()
    {

    }

    private function processInput($key, $type)
    {
        $value = $this->input($key);

        return match ($type) {
            'phone' => $this->processPhone($value),
            'decimal' => $this->processDecimal($value),
        };
    }

    protected function processPhone($value)
    {
        return str_replace('-', '', $value);
    }

    protected function processDecimal($value)
    {
        return str_replace(',', '', $value);
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
}
