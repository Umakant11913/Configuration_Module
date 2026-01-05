<?php

namespace App\Providers;

use App\Services\ZohoAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Lunaweb\RecaptchaV3\RecaptchaV3;
use App\Services\ApMqttService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ZohoAuth::class, function ($app) {
            return new ZohoAuth();
        });

        $this->app->singleton(ApMqttService::class, function ($app) {
            return new ApMqttService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        $this->app->singleton('recaptcha', function ($app) {
            return new RecaptchaV3(
                config('recaptchav3.sitekey'),
                config('recaptchav3.secret')
            );
        });
    }
}
