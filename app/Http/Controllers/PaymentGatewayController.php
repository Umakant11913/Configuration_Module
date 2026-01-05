<?php

namespace App\Http\Controllers;

use App\Models\BrandingProfile;
use App\Models\InternetPlan;
use App\Models\Location;
use App\Models\PdoPaymentGateway;
use App\Models\User;
use App\Models\WiFiOrders;
use App\Models\ZoneInternetPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class PaymentGatewayController extends Controller
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

    public function initiate(Request $request)
    {
        $internet_plan = InternetPlan::find($request->internet_plan_id);
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $amount = (int)round($internet_plan->price * 100);

        $data = [
            'amount' => $internet_plan->price,
            'phone' => $user->phone,
            'user_id' => $user->id,
            'location_id' => $request->location_id,
            'internet_plan_id' => $internet_plan->id,
        ];
        if (isset($data['location_id'])) {
            $data['owner_id'] = Location::find($data['location_id'])->owner_id;
        }

        $wifiOrder = WiFiOrders::create($data);

        $razorpayOrder = $this->api->order->create(array('amount' => $amount, 'currency' => 'INR', 'notes' => array('order_id' => $wifiOrder->id)));

        $wifiOrder->order_reference = $razorpayOrder->id;
        $wifiOrder->save();

        $order = ['id' => $razorpayOrder->id, 'amount' => $razorpayOrder->amount, 'wifi_order' => $wifiOrder];

        return compact('order');
    }

    public function payment(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $json = [
            'amount' => $request->amount,
            'currency' => 'INR',
            'email' => $user->email,
            'contact' => $user->phone,
            'order_id' => $request->order_id,
            'method' => 'card',
            "auth_type" => 'otp',
            'card' => [
                'number' => $request->card_number,
                'cvv' => $request->cvv,
                'expiry_month' => $request->expiry_month,
                'expiry_year' => $request->expiry_year,
                'name' => $request->name_on_card
            ]
        ];
        $payment = $this->api->payment->createPaymentJson($json);

        $action_url = '';
        foreach ($payment->next as $data) {
            if ($data->action == 'otp_generate') {
                $action_url = $data->url;
            }
        }

        if (!$action_url) {
            abort(422, 'Unable to get OTP url');
        }


        $data = $this->generateOtp($payment->razorpay_payment_id, $action_url);

        return response()->json($data);
    }

    private function generateOtp($razorpay_payment_id, string $action_url)
    {
//        $response = $this->api->payment->fetch($razorpay_payment_id)->otpGenerate();

        $response = Http::post($action_url);

        if (!$response->successful()) {
            abort(500, $response->body());
        }

        return $response->json();
    }

    public function resendOtp(Request $request)
    {
        $razorpay_payment_id = $request->razorpay_payment_id;
        $action_url = $request->action_url;
        if (!$razorpay_payment_id) {
            abort(404, 'Payment id not found!');
        }
        if (!$action_url) {
            abort(422, 'Action URL not found');
        }

        $data = $this->generateOtp($razorpay_payment_id, $action_url);
        return response()->json($data);
    }

    public function verifyOtp(Request $request)
    {
        $razorpay_payment_id = $request->razorpay_payment_id;
        $otp = $request->otp;
        if (!$razorpay_payment_id) {
            abort(404, 'Payment id not found!');
        }
        if (!$otp) {
            abort(404, 'OTP is required found!');
        }

        $response = $this->api->payment->fetch($razorpay_payment_id)->otpSubmit(['otp' => $otp]);

        $data = [
            'razorpay_payment_id' => $response->razorpay_payment_id,
            'razorpay_order_id' => $response->razorpay_order_id,
            'razorpay_signature' => $response->razorpay_signature,
        ];

        if ($response->razorpay_order_id) {
            $razorOrder = $this->api->order->fetch($response->razorpay_order_id);
            $notes = $razorOrder->notes;
            $wifiOrderId = $notes['order_id'];
            $wifiOrder = WiFiOrders::find($wifiOrderId);
            $wifiOrder->fill([
                'payment_reference' => $response->razorpay_payment_id,
                'status' => 1,
            ]);
            $wifiOrder->save();
        }

        // Todo: verify signature
        return response()->json($data);
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
}
