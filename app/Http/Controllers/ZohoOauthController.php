<?php

namespace App\Http\Controllers;

use App\Models\ZohoOauthToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use zcrmsdk\crm\setup\restclient\ZCRMRestClient;
use zcrmsdk\oauth\ZohoOAuth;

class ZohoOauthController extends Controller
{
    public function index()
    {
        $scope = 'ZohoInventory.FullAccess.all';
        $scope .= ',Desk.tickets.ALL';
        $scope .= ',Desk.products.CREATE';
        $scope .= ',Desk.products.READ';
        $scope .= ',Desk.products.UPDATE';
        $scope .= ',ZohoBooks.bills.Create';
        $scope .= ',ZohoBooks.bills.UPDATE';
        $scope .= ',ZohoBooks.bills.READ';
        $scope .= ',ZohoBooks.bills.DELETE';
        $scope .= ',ZohoInventory.contacts.CREATE';
        $scope .= ',ZohoInventory.contacts.READ';
        $scope .= ',ZohoInventory.contacts.UPDATE';
        $scope .= ',ZohoInventory.contacts.DELETE';

        $state = Str::random(12);
        $client_id = config('services.zoho.client_id');
        $client_secret = config('services.zoho.client_secret');

        $response_type = 'code';
        $redirect_uri = route('zoho.callback');
        $access_type = 'offline';
        $prompt = 'consent';

        ZohoOauthToken::create(compact('state'));

        $data = compact(
            'scope',
            'state',
            'client_id',
            'client_secret',
            'response_type',
            'redirect_uri',
            'prompt',
            'access_type'
        );

        return redirect('https://accounts.zoho.com/oauth/v2/auth?' . http_build_query($data));
    }

    public function callback(Request $request)
    {
        $state = $request->state;

        $zohoToken = ZohoOauthToken::where('state', $state)->first();

        $code = $request->code;
        $location = $request->location;
        $server = $request->get('accounts-server');

        $zohoToken->accounts_server = $server;
        $zohoToken->location = $location;
        $zohoToken->grant_code = $code;
        $zohoToken->save();

        $client_id = config('services.zoho.client_id');
        $client_secret = config('services.zoho.client_secret');
        $grant_type = 'authorization_code';
        $redirect_uri = route('zoho.callback');
        $data = compact('code', 'client_id', 'grant_type', 'state', 'redirect_uri', 'client_secret');

        $response = Http::asForm()->connectTimeout(30)->timeout(30)->post($server . '/oauth/v2/token', $data);

        Log::info($response->body());
        Log::info($response->status() . ' - HTTP STATUS');

        $zohoToken->access_token = $response->json('access_token');
        $zohoToken->refresh_token = $response->json('refresh_token');
        $zohoToken->save();

        return redirect('/dashboard');
    }
}
