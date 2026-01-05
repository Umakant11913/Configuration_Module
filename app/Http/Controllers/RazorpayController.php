<?php

namespace App\Http\Controllers;

use App\Models\RazorpayPayment;
use App\Models\InternetPlan;
use App\Models\PaymentOrders;
use App\Models\WiFiUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;
use Exception;
use Illuminate\Support\Facades\Log;

class RazorpayController extends Controller
{
    public function payment($order_id, Request $request)
    {
        $input = $request->all();
        $order = PaymentOrders::where('id', $order_id);
        $api = new Api(env('RAZOR_KEY'), env('RAZOR_SECRET'));
        $payment = $api->payment->fetch($input['razorpay_payment_id']);
        if (count($input) && !empty($input['razorpay_payment_id'])) {
            try {
                $response = $api->payment->fetch($input['razorpay_payment_id'])->capture(array('amount' => $payment['amount']));
                $payment_id = $input['razorpay_payment_id'];
                // $status = $response['']
                // print_r($response);
                // echo "<br />order_id:: ";
                // print_r($order_id);
                $order = PaymentOrders::where('id', $order_id)->first();
                if ($order) {
                    $wifi_user_id = $order->wifi_user_id;
                    $user_info = WiFiUser::where('id', $wifi_user_id)->first();
                    $user_mac = $user_info->mac_address;
                    $internet_plan_id = $order->internet_plan_id;
                    $plan_info = InternetPlan::where('id', $internet_plan_id)->first();
                    $bandwidth = $plan_info->bandwidth;
                    $validity = $plan_info->validity * 60;

                    $radiusWiFiUser = DB::connection('mysql2')->table('radcheck')->where('username', $user_mac)->where('value', $user_mac)->first();
                    $expiration_time = time() + $validity;
                    if ($radiusWiFiUser) {
                        $radiusWiFiUser = DB::connection('mysql2')->table('radcheck')
                            ->where('username', $user_mac)
                            ->update(['expiration_time' => $expiration_time, 'bandwidth' => $bandwidth]);
                    } else {
                        DB::connection('mysql2')->table('radcheck')->insert([
                            'username' => $user_mac,
                            'value' => $user_mac,
                            'attribute' => 'Cleartext-Password',
                            'op' => ':=',
                            'expiration_time' => $expiration_time,
                            'bandwidth' => $bandwidth
                        ]);
                    }


                    return response()->json([
                        'status' => true,
                        'message' => 'Payment Successful',
                        'order' => $order,
                        'request' => $request->all(),
                        'user_mac' => $user_info
                    ], 201);
                }
                // $createOrder = RazorpayPayment::create($otpRequest);

            } catch (Exception $e) {
                //return  $e->getMessage();
                // Session::put('error',$e->getMessage());
                //return redirect()->back();
            }
        }

    }
}
