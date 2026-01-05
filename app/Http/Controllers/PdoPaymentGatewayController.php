<?php

namespace App\Http\Controllers;

use App\Models\BrandingProfile;
use App\Models\Location;
use App\Models\PdoPaymentGateway;
use App\Models\ZoneInternetPlan;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Razorpay\Api\Api;

class PdoPaymentGatewayController extends Controller
{
    protected $razorKey;
    protected $razorSecret;
    protected $api;

    public function __construct()
    {
        $this->razorKey = config('services.razorpay.key');
        $this->razorSecret = config('services.razorpay.secret');
        $this->api = new Api($this->razorKey, $this->razorSecret);
    }

    // For Adding New Payment Gateway
    public function globalPaymentGateway(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            // If user does not exist, return a JSON response
            return response()->json([
                'status' => false,
                'message' => 'User Does Not Exist!',
            ], 200);
        }

        // Assuming 'id' is the primary key of 'PdoNetworkSetting' table
        $pdoPaymentGatewaySettings = PdoPaymentGateway::where('pdo_id', $user->id)->first();
        if (!$pdoPaymentGatewaySettings) {
            // If 'PdoNetworkSetting' record does not exist for the user, create a new one
            $pdoPaymentGatewaySettings = new PdoPaymentGateway();
            $pdoPaymentGatewaySettings->pdo_id = $user->id;
            $pdoPaymentGatewaySettings->secret = $request->secret;
            $pdoPaymentGatewaySettings->key = $request->key;
            $pdoPaymentGatewaySettings->providers = 'razorpay';
            $pdoPaymentGatewaySettings->save();

        } else {
            // If 'PdoNetworkSetting' record exists, update it
            $pdoPaymentGatewaySettings->secret = $request->secret;
            $pdoPaymentGatewaySettings->key = $request->key;
            $pdoPaymentGatewaySettings->save();

        }
        $pdoPaymentGateways = BrandingProfile::where('pdo_id', $user->id)->where('id', $pdoPaymentGatewaySettings->zone_id)->first();

        if ($pdoPaymentGateways != null) {
            $pdoPaymentGateways->global_payment_gateway = 1;
            $pdoPaymentGateways->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'PdoPaymentGateway add successfully!',
            // You can add any additional data needed here
        ], 200);
    }

    // For Getting PDO Payment Gateway Credentials if Exists
    public function loadPdoPaymentGateway()
    {

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User Does Not Exist!',
            ], 200);
        }

        $PdoPaymentGatewaySettings = PdoPaymentGateway::where('pdo_id', $user->id)->first();

        if (!$PdoPaymentGatewaySettings) {
            return response()->json([
                'status' => false,
                'message' => 'Pdo Payment Gateway not found for this user!',
            ], 404);
        }
        $secret = $PdoPaymentGatewaySettings->secret;
        $key = $PdoPaymentGatewaySettings->key;

//        $maskedSecret = substr($secret, 0, 4) . str_repeat('*', strlen($secret) - 8) . substr($secret, -4);
//        $maskedKey = substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
        // Return pdo_id and essid in the response
        return response()->json([
            'status' => true,
            'pdo_id' => $PdoPaymentGatewaySettings->pdo_id,
            'secret' => $secret,
            'key' => $key,
        ]);
    }

    public function pdoPaymentGatewayCredentials(Request $request)
    {
        $locationId = $request->get('location_id');
        $location = Location::where('id', $locationId)->first();

        if (!$location) {
            return response()->json([
                'status' => false,
                'message' => 'User Does Not Exist!',
            ], 200);
        }

        if ($location->owner_id != null) {
            $branding_profile = BrandingProfile::where('pdo_id', $location->owner_id)->first();

            if ($branding_profile) {
                $zone_internet_plan = ZoneInternetPlan::where('branding_profile_id', $branding_profile->id)
                    ->whereNotNull('internet_plan_id')
                    ->first();

                if ($zone_internet_plan && $branding_profile->global_payment_gateway == 1) {
                    $global_payment_gateway = PdoPaymentGateway::where('pdo_id', $branding_profile->pdo_id)
                        ->where('zone_id', null)
                        ->first();

                    if ($global_payment_gateway) {
                        return response()->json([
                            'key' => $global_payment_gateway->key
                        ], 200);
                    }
                } else {
                    $global_payment_gateway = PdoPaymentGateway::where('pdo_id', $branding_profile->pdo_id)
                        ->where('is_enable', 1)
                        ->first();

                    if ($global_payment_gateway) {
                        return response()->json([
                            'payment_gateway_credentials' => $global_payment_gateway
                        ], 200);
                    }
                }
            }
        }
        return response()->json([
            'status' => false,
            'message' => 'Unable to fetch payment gateway information',
        ], 200);

    }

    public function checkPaymentGatewayCredentails(Request $request)
    {
        // For Getting Personal Gateway Credentials

        $locationId = $request->get('location_id');
        $location = Location::where('id', $locationId)->first();
        $locationData = Location::where('id', $locationId)->where('owner_id', $location->owner_id)->first();


        $PdoPaymentGatewaySettings = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', $location->profile_id)->where('is_enable', 1)->first();

        if ($PdoPaymentGatewaySettings) {
            $secret = $PdoPaymentGatewaySettings->secret;
            $key = $PdoPaymentGatewaySettings->key;

            return response()->json([
                'status' => true,
                'pdo_id' => $PdoPaymentGatewaySettings->pdo_id,
                'key' => $key,
            ], 200);
        } else if ($locationData) {
            $PaymentGatewayCredentials = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', null)->first();

            if ($PaymentGatewayCredentials) {
                $secret = $PaymentGatewayCredentials->secret;
                $key = $PaymentGatewayCredentials->key;

                return response()->json([
                    'status' => true,
                    'pdo_id' => $PaymentGatewayCredentials->pdo_id,
                    'key' => $key,
                ], 200);
            }
        }

        return response()->json([
            'status' => false,
            'pdo_id' => $location->owner_id,
            'key' => null,
        ], 404);
    }

    public function validateRazorpayKeysOld() {
        $client = new Client();

        $key = 'rzp_te_XMRTmme0WAD0nC';
        $secret = 'gvSSiC1rVyJacQaj2zztwdVT';

        $response = $client->get('https://api.razorpay.com/v1/account', [
            'auth' => [$key, $secret]
        ]);

        if ($response->getStatusCode() === 200) {
            return 'Razorpay key and secret are valid.';
        } else {
            return  'Razorpay key and secret are not valid.';
        }
    }

    public function validateRazorpayKeys(Request $request) {
        $key = $request->key;
        $secret = $request->secret;

        if(!isset($key) && !isset($secret)){
            return response()->json([
                'status' => false,
                'message' => 'Razorpay key & secret are required.',
            ]);
        }

        $response = Http::withBasicAuth($key, $secret)
            ->get('https://api.razorpay.com/v1/orders');

        if ($response->successful()) {
            //return $response->json();
            return response()->json([
                'status' => true,
                'message' => 'Razorpay key and secret are valid.',
            ],200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Razorpay key and secret are not valid.',
            ],200);
        }
    }}
