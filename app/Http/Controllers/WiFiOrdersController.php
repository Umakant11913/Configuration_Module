<?php

namespace App\Http\Controllers;

use App\Jobs\UpdatePayableToZohoBook;
use App\Models\BrandingProfile;
use App\Models\Payout;
use App\Models\PdoPaymentGateway;
use App\Models\Router;
use App\Models\User;
use App\Models\UserAcquisition;
use App\Models\WiFiOrders;
use App\Models\WiFiUser;
use App\Models\Location;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use Illuminate\Http\Request;
use App\Models\InternetPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Razorpay\Api\Api;
use SimpleXMLElement;

class WiFiOrdersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function __construct()
    {
        $this->razorKey = config('services.razorpay.key');
        $this->razorSecret = config('services.razorpay.secret');
        $this->api = new Api($this->razorKey, $this->razorSecret);
    }

    public function process_payment(Request $request)
    {
        $data = [
            'order_id' => $request->order_id,
            'payment_id' => $request->razorpay_payment_id,
            'amount' => $request->totalAmount,
            'product_id' => $request->product_id,
            'location_id' => $request->location_id,
            'plan_id' => $request->plan_id,
        ];


        $plan = InternetPlan::where('id', $request->plan_id)->first();
        $locationId = $request->get('location_id');
        $location = Location::where('id', $locationId)->first();
        //$locationData = Location::where('id', $locationId)->where('owner_id', $location->owner_id)->first();

        $personalGateway = "";
        $GlobalPayment = "";
        if(isset($location) && $location->owner_id) {
            $personalGateway = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', $location->profile_id)->where('is_enable', 1)->first();
            $GlobalPayment = PdoPaymentGateway::where('pdo_id', $location->owner_id)->whereNull('zone_id')->first();
        }


        //For PM-Wani Plans
        if ($plan->plan_type == 'default' || $plan->plan_type == null) {
            $this->razorKey = config('services.razorpay.key');
            $this->razorSecret = config('services.razorpay.secret');
            $this->api = new Api($this->razorKey, $this->razorSecret);
        } // For Zone Internet Plans
        else if ($plan->plan_type == 'free' || $plan->plan_type == 'paid') {
            if ($personalGateway !== "") {

                $secret = $personalGateway->secret;
                $key = $personalGateway->key;
                $pdoAPI = new Api($key, $secret);

            } else if (empty($plan->zone_id) && $plan->is_enable == 0 && $GlobalPayment !== "") {

                $secret = $GlobalPayment->secret;
                $key = $GlobalPayment->key;
                $pdoAPI = new Api($key, $secret);

            } else {
                $this->razorKey = config('services.razorpay.key');
                $this->razorSecret = config('services.razorpay.secret');
                $this->api = new Api($this->razorKey, $this->razorSecret);
            }

        } // Location not assigned to Zone
        else {
            $this->razorKey = config('services.razorpay.key');
            $this->razorSecret = config('services.razorpay.secret');
            $this->api = new Api($this->razorKey, $this->razorSecret);
        }

//        if (isset($locationId) && $locationId != '0') {
//            $PdoPaymentGatewaySettings = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', $location->profile_id)->where('is_enable', 1)->first();
//
//            if ($PdoPaymentGatewaySettings) {
//                $secret = $PdoPaymentGatewaySettings->secret;
//                $key = $PdoPaymentGatewaySettings->key;
//
//                $pdoAPI = new Api($key, $secret);
//            } else if ($locationData) {
//                $PaymentGatewayCredentials = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', null)->where('key', '!=', null)->where('secret', '!=', null)->first();
//
//                if ($PaymentGatewayCredentials) {
//                    $secret = $PaymentGatewayCredentials->secret;
//                    $key = $PaymentGatewayCredentials->key;
//
//                    $pdoAPI = new Api($key, $secret);
//                }
//            }
//        }

        $razorPayAPI = $pdoAPI ?? $this->api;

        $order = WiFiOrders::find($request->order_id);
        /*if($order){
            $order->payment_reference = $request->razorpay_payment_id;
            $order->status = 1;
            $order->save();

            $plan_id = $order->internet_plan_id;
            // Update RADIUS DB as per payment status



            $arr = array('msg' => 'Payment successfully credited', 'status' => true);

            return response()->json([
                'success' => true,
                'message' => "WiFi Order Processed",
                'data' => $order,
                'request' => $request->all()
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'WiFi Order creation failed',
                'validated' => $request->all()
            ], 400);
        }
        */

        if ($order) {

            $razorpayPayment = $razorPayAPI->payment->fetch($request->razorpay_payment_id);

            $order->payment_reference = $request->razorpay_payment_id;
            $order->status = 1;
            if ($razorpayPayment->status == "captured") {
                $order->payment_status = "paid";
            } else {
                $order->payment_status = "pending";
            }
            $order->save();

            try {
                $plan = $this->processOrder($order);
            } catch (Exception $ex) {
                return response()->json([
                    'success' => false,
                    'message' => 'WiFi Order creation failed',
                    'validated' => $request->all(),
                    'exception' => $ex
                ], 400);
            }

            $arr = array('msg' => 'Payment successfully credited', 'status' => true);

            return response()->json([
                'success' => true,
                'message' => "WiFi Order Processed",
                'data' => $order,
                'request' => $request->all(),
                'plan' => $plan
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'WiFi Order creation failed',
                'validated' => $request->all()
            ], 400);
        }

    }

    public function process_payment_with_verify(Request $request)
    {
        $data = [
            'order_id' => $request->order_id,
            'payment_id' => $request->razorpay_payment_id,
            'amount' => $request->totalAmount,
            'product_id' => $request->product_id,
            'location_id' => $request->location_id,
        ];

        $order = WiFiOrders::find($request->order_id);

        $razorPayId = $request->razorpay_payment_id;

        $ch = curl_init('https://api.razorpay.com/v1/payments/' . $razorPayId . '');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_USERPWD, "rzp_live_MThYpO72j4C7SK:b1vFV1nmLB78fx99Md0vzUex");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($ch));

        /*
        return response()->json([
            'success' => true,
            'message' => "WiFi Order Processed",
            'data' => $order,
            'request' => $request->all(),
            'razorpay_response' => $response
        ], 200);


		$response->status; // authorized

        /*if($order){
            $order->payment_reference = $request->razorpay_payment_id;
            $order->status = 1;
            $order->save();

            $plan_id = $order->internet_plan_id;
            // Update RADIUS DB as per payment status



            $arr = array('msg' => 'Payment successfully credited', 'status' => true);

            return response()->json([
                'success' => true,
                'message' => "WiFi Order Processed",
                'data' => $order,
                'request' => $request->all()
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'WiFi Order creation failed',
                'validated' => $request->all()
            ], 400);
        }
        */

        if ($order && ($response->status == 'authorized')) {
            $api = new Api(env('RAZOR_KEY'), env('RAZOR_SECRET'));
            $razorpay_response = $api->payment->fetch($razorPayId)->capture(array('amount' => 100 * $request->totalAmount));
            $order->payment_reference = $request->razorpay_payment_id;
            $order->status = 1;
            $order->save();

            $ch = curl_init('https://api.razorpay.com/v1/payments/' . $razorPayId . '');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_USERPWD, "rzp_live_MThYpO72j4C7SK:b1vFV1nmLB78fx99Md0vzUex");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $final_response = json_decode(curl_exec($ch));

            try {
                $plan = $this->processOrder($order);
            } catch (Exception $ex) {
                return response()->json([
                    'success' => false,
                    'message' => 'WiFi Order creation failed',
                    'validated' => $request->all(),
                    'order' => $order,
                    'exception' => $ex
                ], 400);
            }
            /*
            $location_info = Location::where('id', $request->location_id)->first();

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
            $arr = array('msg' => 'Payment successfully credited', 'status' => true);

            return response()->json([
                'success' => true,
                'message' => "WiFi Order Processed",
                'data' => $order,
                'request' => $request->all(),
                'plan' => $plan
                // 'final_response' => $final_response,
                // 'razorpay_response' => $razorpay_response
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'WiFi Order creation failed',
                'validated' => $request->all()
            ], 400);
        }

    }

    public function process_account_payment(Request $request)
    {
        $data = [
            'order_id' => $request->order_id,
            'payment_id' => $request->razorpay_payment_id,
            'amount' => $request->totalAmount,
            'product_id' => $request->product_id,
        ];

        $order = WiFiOrders::find($request->order_id);

        if ($order) {

            $order->payment_reference = $request->razorpay_payment_id;
            $order->status = 1;
            $order->save();

            $user = Auth::user();
            if ($user->parent_id) {
                $parent = User::where('id', $user->parent_id)->first();
                $user = $parent;
            }

            try {
                $plan = $this->processOrder($order, $user);
            } catch (Exception $ex) {
                return response()->json([
                    'success' => false,
                    'message' => 'WiFi Order creation failed',
                    'validated' => $request->all(),
                    'exception' => $ex
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => "WiFi Order Processed",
                'data' => $order,
                'request' => $request->all(),
                'plan' => $plan
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'WiFi Order creation failed',
                'validated' => $request->all()
            ], 400);
        }
    }

    public function create(Request $request)
    {
        $location = Location::where('id', $request->location_id)->first();
        $pdoAPI = null;
        $GlobalPayment = null;
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:10',
            'internet_plan_id' => 'required',
            //'amount' => 'required|integer',
            'owner_id' => 'integer|nullable',
            'location_id' => 'integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ValidationError',
                'data' => $validator->errors(),
            ]);
        }
        $validated = $validator->validated();
        //$validated['plan']
        $plan = InternetPlan::where('id', $request->internet_plan_id)->first();

        //Log::info('add_on_type '.$request->add_on_type);
        $validated['amount'] = $plan->price;
        $validated['payment_status'] = "pending";
        if ($request->add_on_type == 'add-on-plan') {
            $validated['add_on_type'] = 1;
        } else {
            $validated['add_on_type'] = 0;
        }
        if (isset($validated['location_id']) & $validated['location_id'] != 0) {
            $validated['owner_id'] = Location::find($validated['location_id'])->owner_id;
        }

        $wifi_order = WiFiOrders::create($validated);
        $amount = (int)($plan->price * 100);

        $wifiOrderData = WiFiOrders::where('location_id', $wifi_order->location_id)->first();
        $status = false;
        $paymentGatewayKey = null;

        if($request->location_id !== '0' && $request->location_id !== null) {
            $personalGateway = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', $location->profile_id)->where('is_enable', 1)->first();
            $GlobalPayment = PdoPaymentGateway::where('pdo_id', $location->owner_id)->whereNull('zone_id')->first();
        }
        

        // If Order is set then it must go forward
        //if (isset($wifiOrderData)){
        if (isset($wifiOrderData) && $request->location_id != '0') {    
            //For PM-Wani Plans
            if ($plan->plan_type == 'default' || $plan->plan_type == null) {

                $this->razorKey = config('services.razorpay.key');
                $this->razorSecret = config('services.razorpay.secret');
                $this->api = new Api($this->razorKey, $this->razorSecret);
                $paymentGatewayKey = $this->razorKey;
                $status = true;
            } // For Zone Internet Plans
            else if ($plan->plan_type == 'paid') {
                if ($personalGateway) {

                    $secret = $personalGateway->secret;
                    $key = $personalGateway->key;

                    $pdoAPI = new Api($key, $secret);
                    $status = true;
                    $paymentGatewayKey = $key;

                } else if (empty($plan->zone_id) && $plan->is_enable == 0 && $GlobalPayment) {

                    $secret = $GlobalPayment->secret;
                    $key = $GlobalPayment->key;

                    $pdoAPI = new Api($key, $secret);
                    $status = true;
                    $paymentGatewayKey = $key;
                }
             else {
                    $this->razorKey = config('services.razorpay.key');
                    $this->razorSecret = config('services.razorpay.secret');
                    $this->api = new Api($this->razorKey, $this->razorSecret);
                    $paymentGatewayKey = $this->razorKey;
                    $status = true;
                }

            }
            else if($plan->plan_type == 'free') {
            $wifi_order->payment_gateway_type = NULL;
                $wifi_order->status = true;
                $wifi_order->payment_status = 'free';
                $wifi_order->payment_method = 'free';
                $wifi_order->payment_method1 = 'free';
                $wifi_order->save();

                $this->processOrder($wifi_order,  null);
                return response()->json([
                    'success' => true,
                    'pdoPaymentStatus' => true,
                    'message' => "WiFi Order generated",
                    'data' => $wifi_order,
                    'request' => $validated,
                    'pdoPaymentKey' => null,
                ], 200);            }
 // Location not assigned to Zone
            else {
                $this->razorKey = config('services.razorpay.key');
                $this->razorSecret = config('services.razorpay.secret');
                $this->api = new Api($this->razorKey, $this->razorSecret);
                $paymentGatewayKey = $this->razorKey;
                $status = true;
            }
        }

        $razorPayAPI = $pdoAPI ?? $this->api;
        $razorpayOrder = $razorPayAPI->order->create(array('amount' => $amount, 'currency' => 'INR', 'notes' => array('order_id' => $wifi_order->id)));


        $wifi_order->order_reference = $razorpayOrder->id;
        $wifi_order->payment_gateway_type = $pdoAPI ? 'pdo' : 'default';
        $wifi_order->save();

        if ($wifi_order) {
            return response()->json([
                'success' => true,
                'pdoPaymentStatus' => $status,
                'message' => "WiFi Order generated",
                'data' => $wifi_order,
                'request' => $validated,
                'pdoPaymentKey' => $paymentGatewayKey,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'pdoPaymentStatus' => $status,
                'pdoPaymentKey' => $paymentGatewayKey,
                'message' => 'WiFi Order creation failed',
                'validated' => $validated
            ], 400);
        }
    }

    public function verifyPayment(Request $request)
    {
        $event = $request;

        if ($event['event'] === 'payment.captured') {
            $paymentId = $event['payload']['payment']['entity']['id'];
            $orderId = $event['payload']['payment']['entity']['order_id'];

            $payment = $this->api->order->fetch($orderId)->payments();
            $userPayment = WiFiOrders::where('order_reference', $orderId)->first();

            if ($userPayment) {
                if ($payment->items[0]->status === 'captured') {
                    $userPayment->status = 1;
                    $userPayment->payment_status = "paid";
                    $userPayment->payment_reference = isset($payment->items[0]) ? $payment->items[0]->id : null;
                    $userPayment->save();
                    $plan = $this->processOrder($userPayment);
                    return response()->json(['message' => 'WiFi Order captured successfully!'], 200);
                }

                if ($payment->items[0]->status === 'failed') {
                    $userPayment->status = 0;
                    $userPayment->payment_status = "pending";
                    $userPayment->save();
                    return response()->json(['message' => 'WiFi Order payment failed!'], 200);
                }
            } else {
                return response()->json(['message' => 'WiFi Order not found'], 404);
            }
        }

    }

    public function updatePaymentStatus(Request $request)
    {
        $wifiOrders = WiFiOrders::where('payment_status', 'pending')->where('payment_method','!=','bsnl payment gateway')->get();
        $successMessages = [];
        $errorMessages = [];
        foreach ($wifiOrders as $wifiOrder) {
            $pdoAPI = null;
            $key = "";
            $secret = "";

            $createdAt = Carbon::parse($wifiOrder->created_at);
            $nextFiveMinutes = $createdAt->addMinutes(5);
            $plan = InternetPlan::where('id', $wifiOrder->internet_plan_id)->first();
            $location = Location::where('id', $wifiOrder->location_id)->first();
            $personalGateway= "";
            $GlobalPayment = "";

            if($request->location_id !== '0' && $request->location_id !== null) {
                $personalGateway = PdoPaymentGateway::where('pdo_id', $location->owner_id)->where('zone_id', $location->profile_id)->where('is_enable', 1)->first();
                $GlobalPayment = PdoPaymentGateway::where('pdo_id', $location->owner_id)->whereNull('zone_id')->first();
            }

            if ($plan->plan_type == 'default' || $plan->plan_type == null) {
                $this->razorKey = config('services.razorpay.key');
                $this->razorSecret = config('services.razorpay.secret');
                $this->api = new Api($this->razorKey, $this->razorSecret);
            } // For Zone Internet Plans
            else if ($plan->plan_type == 'free' || $plan->plan_type == 'paid') {
                if (!empty($personalGateway)) {
                    $secret = $personalGateway->secret;
                    $key = $personalGateway->key;
                    $pdoAPI = new Api($key, $secret);

                } else if (empty($plan->zone_id) && $plan->is_enable == 0 && $GlobalPayment) {

                    $secret = $GlobalPayment->secret;
                    $key = $GlobalPayment->key;

                    $pdoAPI = new Api($key, $secret);
                } else {
                    $this->razorKey = config('services.razorpay.key');
                    $this->razorSecret = config('services.razorpay.secret');
                    $this->api = new Api($this->razorKey, $this->razorSecret);
                }

            } // Location not assigned to Zone
            else {
                $this->razorKey = config('services.razorpay.key');
                $this->razorSecret = config('services.razorpay.secret');
                $this->api = new Api($this->razorKey, $this->razorSecret);
            }
//            if (isset($locationId) && $locationId != '0') {
//                $location = Location::where('id', $locationId)->first();
//                $pdoId = $location->owner_id;
//                $zoneId = $location->profile_id;
//
//                $zonePaymentGatewayCredentials = PdoPaymentGateway::where('pdo_id', $pdoId)->where('zone_id', $zoneId)->where('is_enable', true)->first();
//                $pdoPaymentGatewayCredentials = PdoPaymentGateway::where('pdo_id', $pdoId)->first();
//
//
//                if (isset($zonePaymentGatewayCredentials)) {
//                    $key = $zonePaymentGatewayCredentials->key;
//                    $secret = $zonePaymentGatewayCredentials->secret;
//                } else if (isset($pdoPaymentGatewayCredentials)) {
//                    $key = $pdoPaymentGatewayCredentials->key;
//                    $secret = $pdoPaymentGatewayCredentials->secret;
//                }
//
//                if (isset($key) && isset($secret)) {
//                    $pdoAPI = new Api($key, $secret);
//                }
//            }
            $razorPayAPI = $pdoAPI ?? $this->api;
            if (Carbon::now()->gte($nextFiveMinutes)) {
                // Order has been pending for more than or equal to five minutes, mark it as cancelled
                $wifiOrder->update(['payment_status' => 'cancelled']);
                $errorMessages[] = 'WiFi Order payment is cancelled';
            } else {
                if ($wifiOrder->payment_reference) {
                    $paymentId = $wifiOrder->payment_reference;
                    //check if its location's PDO has enabled his payment gateway

                    $payment = $razorPayAPI->payment->fetch($paymentId);
                    if ($payment->status === 'captured') {
                        // Payment is successful, update the order status
                        $wifiOrder->update(['payment_status' => 'paid', 'status' => 1,]);
                        $plan = $this->processOrder($wifiOrder);
                        $successMessages[] = 'WiFi Order payment is captured';
                    }
                } else {
                    $orderId = $wifiOrder->order_reference;
                    $order = $razorPayAPI->order->fetch($orderId);

                    $orderPayment = $razorPayAPI->order->fetch($orderId)->payments();

                    if (isset($order) && $order->status === 'paid') {
                        // Payment is successful, update the order status
                        $wifiOrder->update(['status' => 1, 'payment_status' => 'paid', 'payment_reference' => isset($orderPayment->items[0]) ? $orderPayment->items[0]->id : null]);
                        $plan = $this->processOrder($wifiOrder);
                        $successMessages[] = 'WiFi Order payment is captured';
                    }

                    if (isset($order) && ($order->status === 'created' || $order->status === 'attempted')) {
                        $wifiOrder->update(['status' => 0, 'payment_status' => 'pending']);
                        $successMessages[] = 'WiFi Order payment is pending';
                    }
                }
            }
        }

        // Return response after processing all WiFi orders
        if (!empty($errorMessages)) {
            return response()->json(['errors' => $errorMessages], 400);
        } else {
            return response()->json(['success' => $successMessages], 200);
        }
    }

    /*   public function updatePaymentStatus(Request $request){
            $wifiOrders = WiFiOrders::where('payment_status', 'pending')->get();
            $successMessages = [];
            $errorMessages = [];

            foreach ($wifiOrders as $wifiOrder) {
                $createdAt = Carbon::parse($wifiOrder->created_at);
                $nextFiveMinutes = $createdAt->addMinutes(5);

                if (Carbon::now()->gte($nextFiveMinutes)) {
                    // Order has been pending for more than or equal to five minutes, mark it as cancelled
                    $wifiOrder->update(['payment_status' => 'cancelled']);
                    $errorMessages[] = 'WiFi Order payment is cancelled';
                } else {
                    if($wifiOrder->payment_reference) {
                        $paymentId = $wifiOrder->payment_reference;
                        $payment = $this->api->payment->fetch($paymentId);
                        if ($payment->status === 'captured') {
                            // Payment is successful, update the order status
                            $wifiOrder->update(['payment_status' => 'paid', 'status' => 1, ]);
                            $plan = $this->processOrder($wifiOrder);
                            $successMessages[] = 'WiFi Order payment is captured';
                        }
                    } else {
                        $orderId = $wifiOrder->order_reference;
                        $order = $this->api->order->fetch($orderId);
                        $orderPayment = $this->api->order->fetch($orderId)->payments();

                        if ($order->status === 'paid') {
                            // Payment is successful, update the order status
                            $wifiOrder->update(['status' => 1, 'payment_status' => 'paid', 'payment_reference' => isset($orderPayment->items[0]) ? $orderPayment->items[0]->id : null]);
                            $plan = $this->processOrder($wifiOrder);
                            $successMessages[] = 'WiFi Order payment is captured';
                        }

                        if($order->status === 'created' || $order->status === 'attempted') {
                            $wifiOrder->update(['status' => 0, 'payment_status' => 'pending']);
                            $successMessages[] = 'WiFi Order payment is pending';
                        }
                    }
                }
            }

            // Return response after processing all WiFi orders
            if (!empty($errorMessages)) {
                return response()->json(['errors' => $errorMessages], 400);
            } else {
                return response()->json(['success' => $successMessages], 200);
            }
        }*/

    public function accountOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'internet_plan_id' => 'required',
            'location_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ValidationError',
                'data' => $validator->errors(),
            ], 422);
        }
        $validated = $validator->validated();
        //$validated['plan']
        $plan = InternetPlan::where('id', $request->internet_plan_id)->first();
        $validated['amount'] = $plan->price;
        if (isset($validated['location_id'])) {
            $validated['owner_id'] = Location::find($validated['location_id'])->owner_id;
        }

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $validated['phone'] = $user->phone;
        $validated['user_id'] = $user->id;

        $wifi_order = WiFiOrders::create($validated);

        if ($wifi_order) {
            return response()->json([
                'success' => true,
                'message' => "WiFi Order generated",
                'data' => $wifi_order,
                'request' => $validated,
                'user' => $user,
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'WiFi Order creation failed',
                'validated' => $validated
            ], 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function info($id)
    {
        $wifi_order = WiFiOrders::find($id);
        //$wifi_order->date = \Carbon\Carbon::createFromFormat('m/d/Y', $wifi_order->created_at)
        //->format('Y-m-d');
        //$wifi_order->created_at;
        // $date = \Carbon\Carbon::parse($wifi_order->created_at->diffForHumans());

        $wifi_order->order_date = $wifi_order->created_at->format('d-m-Y H:i:s'); //$date->format("Y-m-d H:i:s");

        $internet_plan_details = InternetPlan::where('id', $wifi_order->internet_plan_id)->first();
        $wifi_order->internet_plan_name = $internet_plan_details->name;
        $wifi_order->internet_plan_description = $internet_plan_details->description;

        return response()->json([
            'success' => true,
            'message' => "WiFi Order details retrived",
            'data' => $wifi_order
        ], 200);
    }

    public function all_orders(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // Search value
        $startDate = $request->get('StartDate');
        $endDate = $request->get('EndDate');
        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;

        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $totalRecords = "";
        $totalRecordswithFilter = "";
        $orders = '';

        $totalRecordsWithoutFilter = DB::table('wi_fi_orders')
            ->leftJoin('internet_plans', 'wi_fi_orders.internet_plan_id', '=', 'internet_plans.id')
            ->leftJoin('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
            ->leftJoin('users', 'locations.owner_id', '=', 'users.id');

        $totalDisplayRecordsWithoutFilter = DB::table('wi_fi_orders')
            ->leftJoin('internet_plans', 'wi_fi_orders.internet_plan_id', '=', 'internet_plans.id')
            ->leftJoin('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
            ->leftJoin('users', 'locations.owner_id', '=', 'users.id')
            ->where(function ($query) use ($searchValue) {
                $query->where('wi_fi_orders.phone', 'like', '%' . $searchValue . '%')
                    ->orWhere('wi_fi_orders.amount', 'like', '%' . $searchValue . '%')
                    ->orWhere('wi_fi_orders.payment_status', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('locations.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.last_name', 'like', '%' . $searchValue . '%');
            });

        if (!empty($startDate) && !empty($endDate)) {
            if ($user->isPDO()) {
                $totalRecords = $totalRecordsWithoutFilter->where('locations.owner_id', Auth::user()->id)->distinct()->count();

                $totalRecordswithFilter = $totalDisplayRecordsWithoutFilter->where('locations.owner_id', Auth::user()->id)
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])->count();

                $orders = $totalDisplayRecordsWithoutFilter->where('locations.owner_id', Auth::user()->id)
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->orderBy($columnName, $columnSortOrder)
                    ->select('wi_fi_orders.phone as phone_number', 'wi_fi_orders.created_at as created_at', 'wi_fi_orders.id as id', 'users.first_name as first_name', 'users.last_name as last_name', 'users.id as owners_id', 'amount', 'wi_fi_orders.created_at as date', 'locations.id as locations_id', 'locations.name as location_name', 'wi_fi_orders.status as status', 'payment_status', 'payout_status', 'wi_fi_orders.petals as petals', 'payment_method', 'internet_plans.id as internet_plans_id', 'internet_plans.name as internet_plans_name', 'internet_plans.price as internet_plans_price')
                    ->skip($start)
                    ->take($rowperpage)
                    ->get();
            } else {
                $totalRecords = $totalRecordsWithoutFilter->distinct()->count();

                $totalRecordswithFilter = $totalDisplayRecordsWithoutFilter->whereBetween('wi_fi_orders.created_at',
                    [$formattedStartDate, $formattedEndDate])->count();

                $orders = $totalDisplayRecordsWithoutFilter->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->orderBy($columnName, $columnSortOrder)
                    ->select('wi_fi_orders.phone as phone_number', 'wi_fi_orders.created_at as created_at', 'wi_fi_orders.id as id', 'users.first_name as first_name', 'users.last_name as last_name', 'users.id as owners_id', 'amount', 'wi_fi_orders.created_at as date', 'locations.id as locations_id', 'locations.name as location_name', 'wi_fi_orders.status as status', 'payment_status', 'payout_status', 'wi_fi_orders.petals as petals', 'payment_method', 'internet_plans.id as internet_plans_id', 'internet_plans.name as internet_plans_name', 'internet_plans.price as internet_plans_price')
                    ->skip($start)
                    ->take($rowperpage)
                    ->get();
            }
        } else {
            if ($user->isPDO()) {
                $totalRecords = $totalRecordsWithoutFilter->where('locations.owner_id', Auth::user()->id)->distinct()->count();

                $totalRecordswithFilter = $totalDisplayRecordsWithoutFilter->where('locations.owner_id', Auth::user()->id)->count();

                $orders = $totalDisplayRecordsWithoutFilter->where('locations.owner_id', Auth::user()->id)
                    ->orderBy($columnName, $columnSortOrder)
                    ->select('wi_fi_orders.phone as phone_number', 'wi_fi_orders.id as id', 'users.first_name as first_name', 'users.last_name as last_name', 'users.id as owners_id', 'amount', 'wi_fi_orders.created_at as date', 'locations.id as locations_id', 'locations.name as location_name', 'wi_fi_orders.status as status', 'payment_status', 'payout_status', 'wi_fi_orders.petals as petals', 'payment_method', 'internet_plans.id as internet_plans_id', 'internet_plans.name as internet_plans_name', 'internet_plans.price as internet_plans_price')
                    ->skip($start)
                    ->take($rowperpage)
                    ->get();
            } else {
                $totalRecords = $totalRecordsWithoutFilter->distinct()->count();

                $totalRecordswithFilter = $totalDisplayRecordsWithoutFilter->count();

                $orders = $totalDisplayRecordsWithoutFilter->orderBy($columnName, $columnSortOrder)
                    ->select('wi_fi_orders.phone as phone_number', 'wi_fi_orders.id as id', 'users.first_name as first_name', 'users.last_name as last_name', 'users.id as owners_id', 'amount', 'wi_fi_orders.created_at as date', 'locations.id as locations_id', 'locations.name as location_name', 'wi_fi_orders.status as status', 'payment_status', 'payout_status', 'wi_fi_orders.petals as petals', 'payment_method', 'internet_plans.id as internet_plans_id', 'internet_plans.name as internet_plans_name', 'internet_plans.price as internet_plans_price')
                    ->skip($start)
                    ->take($rowperpage)
                    ->get();
            }
        }

        $data_arr = array();

        foreach ($orders as $order) {
            $id = $order->id;
            $name = $order->first_name;
            $phone = $order->phone_number;
            $owner_id = $order->owners_id;
            $amount = $order->amount == $order->internet_plans_price ? $order->amount : $order->amount / 100;
            $internet_plans_id = $order->internet_plans_id;
            $internet_plans_name = $order->internet_plans_name;
            $date = $order->date;
            $location_id = $order->locations_id;
            $location_name = $order->location_name;
            $status = $order->status;
            $payout_status = $order->payout_status;
            $payment_status = $order->payment_status;
            $payment_method = $order->payment_method;
            $petals = $order->petals;

            $data_arr[] = array(
                "id" => $id,
                "first_name" => $name,
                "phone_number" => $user->isPDO() ? substr_replace($phone, str_repeat('*', strlen($phone) - 6), 4, -2) : $phone,
                "owners_id" => $owner_id,
                "amount" => $amount,
                "internet_plans_id" => $internet_plans_id,
                "internet_plans_name" => $internet_plans_name,
                "date" => $date,
                "location_id" => $location_id,
                "location_name" => $location_name,
                "status" => $status,
                "payout_status" => $payout_status,
                "payment_status" => $payment_status,
                "payment_method" => $payment_method,
                "petals" => $petals
            );
        }


        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        );

        return response()->json($response);
    }

    /*public function all_orders(Request $request)
    {
        $query = WiFiOrders::query()->with('internetPlan', 'location.owner');
        if ($request->filter == 'all') {
            $query->mine();
        }
        return $query->orderBy('id', 'DESC')->get();
    }*/

    public function forCustomer(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $query = WiFiOrders::query()->with('internetPlan', 'location.owner');
        $query->where('phone', $user->phone);
        return $query->orderBy('id', 'DESC')->get();
    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\WiFiOrders $wiFiOrders
     * @return \Illuminate\Http\Response
     */
    public function show(WiFiOrders $wiFiOrders)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\WiFiOrders $wiFiOrders
     * @return \Illuminate\Http\Response
     */
    public function edit(WiFiOrders $wiFiOrders)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\WiFiOrders $wiFiOrders
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WiFiOrders $wiFiOrders)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\WiFiOrders $wiFiOrders
     * @return \Illuminate\Http\Response
     */
    public function destroy(WiFiOrders $wiFiOrders)
    {
        //
    }

    /**
     * @param $order
     * @return mixed
     */
    private function processOrder($order, $user = null)
    {
        $plan_id = $order->internet_plan_id;
        $plan = InternetPlan::find($plan_id);

        $bandwidth = $plan->bandwidth;
        $session_duration = $plan->session_duration;
        $data_limit = $plan->data_limit;
        $expiration_time = $plan->validity * 60;
        $phone = $order->phone;
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

       // Log::info('wifi_orders count -------> '.$wifiOrders);
        //add on data

        if ($wifiOrders->count() > 1) {
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

    private function oldprocessOrder($order, $user = null)
    {
        $plan_id = $order->internet_plan_id;
        $plan = InternetPlan::find($plan_id);

        $bandwidth = $plan->bandwidth;
        $session_duration = $plan->session_duration;
        $data_limit = $plan->data_limit;
        $expiration_time = $plan->validity * 60;
        $phone = $order->phone;
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


        //add on data
        if ($order->add_on_type == 1 && $radWiFiUser) {

            $add_on_data_limit = $radWiFiUser->data_limit + $data_limit;
            DB::connection('mysql2')->table('radcheck')->where('username', $phone)->update([
                'data_limit' => $add_on_data_limit,
            ]);

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
                                'plan_start_time' => $plan_start_time
                            ]);
                    }

                } else {
                    $expiration_time = time() + $expiration_time;
                    // $plan_start_time = $radWiFiUser->plan_start_time;
                    DB::connection('mysql2')
                        ->table('radcheck')
                        ->where('username', $phone)
                        ->when($add_on_type === 0 || $add_on_type === null, function ($query) use ($add_on_type) {
                            return $query->where('add_on_type', $add_on_type);
                        })
                        ->update([
                            'expiration_time' => $expiration_time,
                            'bandwidth' => $bandwidth,
                            'session_duration' => $session_duration,
                            'data_limit' => $data_limit,
                            'session_duration_window' => $session_duration_window,
                            'plan_start_time' => now()
                        ]);
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

    public function generate_password($length = 4)
    {
        $characters = 'abcdefghijklmnopqrstuvwxy9876543210';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function createBsnlOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:10',
            'internet_plan_id' => 'required',
            'owner_id' => 'integer|nullable',
            'location_id' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ValidationError',
                'data' => $validator->errors(),
            ]);
        }

        $location = Location::find($request->location_id);
        $plan = InternetPlan::find($request->internet_plan_id);
        $router = Router::find($request->macAddress);

        $validated = $validator->validated();
        $validated['amount'] = $plan->price;
        $validated['payment_status'] = "pending";
        if (isset($validated['location_id']) && $validated['location_id'] != 0) {
            $validated['owner_id'] = $location->owner_id;
        }

        $order = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRTUVWXYZ2346789'), 0, 15);
        $wifi_order = WiFiOrders::create($validated);
        $wifi_order->order_reference = 'BSNL' . $order;
        $wifi_order->payment_method = 'bsnl payment gateway';
        $wifi_order->owner_id = Auth::user()->id;
        $wifi_order->save();

        $sessionId = 'BSNL' . $order;

        $data = [
            'SUBSCRIBER_ID' => '91' . $request->phone,
            'SVCTYPE' => 'WFP',
            'AGENCY' => 'INTRAG',
            'VENDOR_TXN_ID' => $wifi_order->id . '_' . Carbon::now()->format('Ymd'),
            'AMOUNT' => $plan->price,
            'DATE_TIME' => Carbon::now()->format('d/m/Y H:i:s'),
            'sessionId' => 'BSNL' . $order,
            'Circle' => $router->circle ?? 'DL',
            'Info1' => $location->name ?? '',
            'Info2' => '',
            'Info3' => '',
            'Info4' => '',
            'ServiceName' => 'Prepaid Wifi',
            'ReturnUrl' => 'https://bsnl.pmwani.net/bsnl-payment'
        ];

        $xmlString = $this->createDynamicXML($data);

	//Log::info($xmlString);
        $response = Http::withHeaders([
            'Content-Type' => 'application/xml',
        ])->withBody($xmlString, 'application/xml')->post('https://portal2.bsnl.in/myportal/PGService/initiaterequest.do');

        if ($response->successful()) {
            return response()->json([
                'status' => $response->status(),
                'body' => $response->body(),
                'sessionId' => $sessionId
            ])->header('Content-Type', 'application/json');
        } else {
            return response()->json([
                'status' => $response->status(),
                'body' => $response->body(),
            ])->header('Content-Type', 'application/json');
        }
    }

    private function createDynamicXML($params)
    {
        $xml = new SimpleXMLElement('<XML/>');

        foreach ($params as $key => $value) {
            $xml->addChild($key, htmlspecialchars($value));
        }

        return $xml->asXML();
    }


    public function orderStatus(Request $request)
    {

        //Log::info("In Order Status API for BSNL");
        $data = json_decode($request->getContent(), true);
	//Log::info(json_encode($data));
        $txnStatus = $data['TXN_STATUS'];
        $subscriberId = $data['SUBSCRIBER_ID'];

        $number = preg_replace("/[^0-9]/", "", $subscriberId);
        $phone_number = substr($number, 2);

        $vendorTxnId = $data['VENDOR_TXN_ID'];
        $portalTxnId = $data['PORTAL_TXN_ID'];
        $bankId = $data['BANK_ID'] ?? '';
        $bankTxnId = $data['BANK_TXN_ID'] ?? '';
        $amount = $data['AMOUNT'] ?? '';
        $dateTime = $data['DATE_TIME'];
        $errorCode = $data['ERROR_CODE'];
        $errorMsg = $data['ERROR_MSG'];
        $sessionId = $data['sessionId'];
        $info1 = $data['Info1'] ?? '';
        $info2 = $data['Info2'] ?? '';
        $info3 = $data['Info3'] ?? '';
        $serviceName = $data['ServiceName'];

        $paymentJson = json_encode($data, true);
        $wifiOrder = WiFiOrders::where('order_reference', $sessionId)->first();

        if ($txnStatus == 'SUCCESS') {
            $wifiOrder->payment_status = 'paid';
            $wifiOrder->payment_reference = $portalTxnId;
            $wifiOrder->payment_details = $paymentJson;
            $wifiOrder->status = 1;
            $wifiOrder->data_used = 0;
            $wifiOrder->save();

            $extraPlansCount = WiFiOrders::where('phone', $phone_number)
                ->where('payment_status', 'paid')->
                where('status', 1)->where('data_used', 0)->count();

            $internetPlan = InternetPlan::where('id', $wifiOrder->internet_plan_id)->first();

            if ($extraPlansCount <= 1) {
		$expirationTime = $internetPlan->validity * 60;
		$expirationTime = time() + $expirationTime;

                $data = [
                    'username' => $phone_number,
                    'value' => $phone_number,
                    'attribute' => 'Cleartext-Password',
                    'op' => ':=',
                    'expiration_time' => $expirationTime,
                    'bandwidth' => $internetPlan->bandwidth,
                    'session_duration' => $internetPlan->session_duration,
                    'session_duration_window' => $internetPlan->session_duration_window,
                    'data_limit' => $internetPlan->data_limit,
                    'plan_start_time' => now()
                ];
                DB::connection('mysql2')->table('radcheck')->insert($data);
            }

            return response()->json([
                'message' => 'success'
            ], 200);

        } elseif ($txnStatus == 'FAILURE') {

            $wifiOrder->payment_status = 'cancelled';
            $wifiOrder->save();

            return response()->json([
                'message' => $errorMsg,
                'status' => 'failure',
            ], 404);

        } else {
            $wifiOrder->payment_status = 'cancelled';
            $wifiOrder->save();

            return response()->json([
                'message' => $errorMsg,
                'status' => 'failure',
            ], 404);
        }
    }

    public function checkOrderStatus(Request $request)
    {
        $sessionId = $request->sessionId;

        $wifiOrder = WiFiOrders::where('order_reference', $sessionId)->first();

        if ($wifiOrder) {
            if ($wifiOrder->payment_status == 'paid') {
                return response()->json([
                    'message' => 'success',
                    'orderId' => $wifiOrder->id
                ], 200);
            } elseif ($wifiOrder->payment_status == 'cancelled') {
                return response()->json([
                    'message' => 'failed',
                    'orderId' => $wifiOrder->id
                ], 200);
            } else {
                return response()->json([
                    'message' => 'pending',
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Invalid Order reference'
            ], 404);
        }
    }

    public function dateConversion($createdAt, $validity)
    {
        $date = new DateTime($createdAt);
        $interval = new DateInterval('PT' . $validity . 'S');
        $date->add($interval);
        return $date->getTimestamp();
    }
}
