<?php

namespace App\Services;

use App\Models\ZohoOauthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZohoAuth
{
    public function refreshToken()
    {
        $zohoToken = ZohoOauthToken::latest()->first();
        if (!$zohoToken || !$zohoToken->refresh_token) {
            abort(400, 'Sorry! Start a fresh Linking of Zoho');
        }

        $client_id = config('services.zoho.client_id');
        $client_secret = config('services.zoho.client_secret');
        $grant_type = 'refresh_token';
        $redirect_uri = route('zoho.callback');
        $refresh_token = $zohoToken->refresh_token;
        $server = $zohoToken->accounts_server;
        $data = compact('client_id', 'grant_type', 'redirect_uri', 'client_secret', 'refresh_token');

        $response = Http::asForm()->connectTimeout(30)->timeout(30)->post($server . '/oauth/v2/token', $data);

        Log::info($response->body());
        Log::info($response->status() . ' - HTTP STATUS');

        if ($response->successful()) {
            $zohoToken->access_token = $response->json('access_token');
            $zohoToken->save();
            return true;
        } else {
            return false;
        }
    }

}
