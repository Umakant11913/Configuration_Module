<?php

namespace App\Http\Controllers;

use App\Models\InternetPlan;
use App\Models\InternetPlanVouchers;
use App\Models\Location;
use App\Models\NetworkSettings;
use App\Models\PaymentExtensions;
use App\Models\PdoNetworkSetting;
use App\Models\URLLogin;
use App\Models\User;
use App\Models\WiFiOrders;
use App\Models\WiFiUser;
use App\Models\ZoneInternetPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InternetPlanVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $planId = $request->planId ?? '';
        $pdoStatus = $request->pdoStatus ?? '';
        $user = Auth::user();

        $plan = InternetPlan::where('id', $planId)->first();

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

        $totalRecords = '';
        $totalRecordswithFilter = '';

        if ($planId) {
            if ($plan->added_by !== Auth::user()->id && $pdoStatus == false) {
                return response()->json([
                    'message' => 'You are not allowed to view this Page',
                ], 404);
            }

            $query = InternetPlanVouchers::where('pdo_id', $user->id)->where('plan_id', $planId)->where(function ($query) use ($searchValue) {
                $query->where('internet_plans_vouchers.title', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans_vouchers.expiry_date', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans_vouchers.status', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans_vouchers.created_at', 'like', '%' . $searchValue . '%');
            });
        } else if ($pdoStatus) {
            $query = InternetPlanVouchers::where('pdo_id', $user->id)->where(function ($query) use ($searchValue) {
                $query->where('internet_plans_vouchers.title', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans_vouchers.expiry_date', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans_vouchers.status', 'like', '%' . $searchValue . '%')
                    ->orWhere('internet_plans_vouchers.created_at', 'like', '%' . $searchValue . '%');
            });
        }

        $totalRecords = $query->count();
        $totalRecordswithFilter = $query->count();

        $vouchers = $query->orderBy('internet_plans_vouchers.created_at', 'desc')
            ->take($rowperpage)
            ->skip($start)
            ->get();

        $data_arr = array();
        foreach ($vouchers as $voucher) {
            $voucherId = $voucher->id;
            $voucherName = $voucher->title ?? null;
            $expiryDate = $voucher->expiry_date;
            $addedOn = $voucher->created_at;
            $plan = $voucher->internetPlans->name;
            $status = $voucher->status;
            $expiringOn = Carbon::create($voucher->expiry_date)->diffForHumans();
            $allocatedPhone = "*******" . substr($voucher->allocated_phone_number, 6, 4);

//            if ($expiryDate < Carbon::today()->format('Y-m-d') && $status == 'expired') {
//                $status = 'Expired';
//            }

            $dateToBeUsed = ($status === "redeemed") ? Carbon::create($voucher->used_on) : Carbon::create($voucher->expiry_date);

            $data_arr[] = array(
                'id' => $voucherId,
                'voucher' => $voucherName,
                'expiry_date' => $expiryDate,
                'created_on' => $addedOn,
                'plan_name' => $plan,
                'status' => $status,
                'expiringIn' => (($status === "redeemed") ? "Redeemed on " : ($dateToBeUsed->isPast() ? "Expired on " : "Expiring on ")) . $dateToBeUsed->format('d F Y'),
                'allocatedPhone' => $allocatedPhone,
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    public function generateVoucherCode()
    {
        $str_result = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($str_result),
            0, 6);
    }

    public function store(Request $request)
    {
        $numberOfVouchers = $request->total_vouchers;
        $expiry = $request->expiry;
        $planId = $request->plan_id;
        $zoneId = ZoneInternetPlan::where('internet_plan_id', $planId)->first();

        for ($i = 0; $i < $numberOfVouchers; $i++) {
            $voucher = InternetPlanVouchers::create([
                'title' => $this->generateVoucherCode(),
                'expiry_date' => $expiry,
                'status' => 'available',
                'pdo_id' => Auth::user()->id,
                'plan_id' => $planId,
                'zone_id' => $zoneId->branding_profile_id ?? NULL,
            ]);
        }

        return response()->json([
            'message' => 'success',
            'count' => $numberOfVouchers,
        ], 200);
    }

    public function showVouchersDetail(Request $request)
    {
        $limit = $request->get('count');

        $user = Auth::user();

        $latestRecords = InternetPlanVouchers::with('internetPlans')->where('pdo_id', $user->id)->orderBy('created_at', 'DESC')->limit($limit)->get();

        return response()->json([
            "message" => 'success',
            'data' => $latestRecords,
        ]);
    }

    public function showSingleVoucherDetails(Request $request)
    {
        $voucherId = $request->voucherId;
        $vouchers = InternetPlanVouchers::with('internetPlans', 'users', 'locations', 'zone')->where('id', $voucherId)->first();
        return $vouchers;
    }

    public function cancelVoucher(Request $request)
    {
        $voucher = InternetPlanVouchers::where('id', $request->voucherId)->first();
        $voucher->status = 'cancelled';
        $voucher->save();
        return response()->json([
            'status' => true,
            'message' => 'Voucher cancelled'
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    function update_radius($phone, $data)
    {
        $username = $phone;
        $wifiuser = DB::connection('mysql2')->table('radcheck')
            ->where('username', $username)
            ->first();
        if ($wifiuser) {
            $radUserGroup = $radUserGroup = DB::connection('mysql2')->table('radusergroup')->where('username', $username)->first();
            $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                ->where('username', $username)
                ->update(array(
                    'data_limit' => $data->data_limit,
                    'expiration_time' => $data->validity != 0 ? time() + $data->validity * 60 : Carbon::now()->addDays(1)->timestamp,
                    'bandwidth' => $data->bandwidth,
                    'session_duration' => $data->session_duration != 0 ? $data->session_duration : 30,
                    'session_duration_window' => $data->session_duration_window ?? 0,
                    'plan_start_time' => now()
                ));
            if ($radUserGroup) {
                $radUserGroup = DB::connection('mysql2')->table('radusergroup')
                    ->where('username', $username)
                    ->update(array(
                        'username' => $username,
                        'groupname' => 'pmwani',
                        'priority' => '100',
                    ));
            } else {
                DB::connection('mysql2')->table('radusergroup')->insert([
                    'username' => $username,
                    'groupname' => 'pmwani',
                    'priority' => '100',
                ]);
            }
        } else {
            $expiration_time = strtotime('today midnight');
            DB::connection('mysql2')->table('radcheck')->insert([
                'username' => $username,
                'value' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'expiration_time' => $data->validity != 0 ? time() + $data->validity * 60 : Carbon::now()->addDays(1)->timestamp,
                'bandwidth' => $data->bandwidth,
                'session_duration' => $data->session_duration != 0 ? $data->session_duration : 30,
                'data_limit' => $data->data_limit,
                'session_duration_window' => $data->session_duration_window ?? 0,
                'plan_start_time' => now()
            ]);
            DB::connection('mysql2')->table('radusergroup')->insert([
                'username' => $username,
                'groupname' => 'pmwani',
                'priority' => '100',
            ]);
        }
    }

    public function checkVoucherStatus(Request $request)
    {
        $requestVoucher = $request->voucher;

        $voucher = InternetPlanVouchers::where('title', $requestVoucher)->where('plan_id', $request->plan)->first();
        if (!$voucher) {
            return response()->json([
                'message' => 'Invalid Coupon Code',
                'status' => false,
            ], 200);
        }

        $title = $voucher->title;
        $titleLength = strlen($title);
        $voucherLength = strlen($requestVoucher);

        $match = true;

        for ($i = 0; $i < min($titleLength, $voucherLength); $i++) {
            if (($title[$i]) !== ($requestVoucher[$i])) {
                $match = false;
                break;
            }
        }

        if ($match) {

            $planId = $voucher->plan_id ?? '';
            $planDetails = InternetPlan::where('id', $planId)->first();
            $challenge = $request->challenge ?? '';

            $locationId = $request->location ?? '';
            $locationDetails = Location::where('id', $locationId)->first();
            $user = User::where('id', Auth::user()->id)->first();

            // For using Available Voucher
            if ($voucher->status == 'available' && $voucher->expiry_date >= today()->format('Y-m-d')) {
                $voucher->used_on = Carbon::today();
                $voucher->user_id = Auth::user()->id;
                $voucher->location_id = $request->location;
                $voucher->status = 'redeemed';
                $voucher->save();

                $wifiOrder = WiFiOrders::create([
                    'phone' => $user->phone,
                    'internet_plan_id' => $planId,
                    'owner_id' => $locationDetails->owner_id,
                    'status' => 1,
                    'amount' => $planDetails->price,
                    'location_id' => $locationId,
                    'user_id' => Auth::user()->id,
                    'add_on_type' => ($planDetails->add_on_type === 'add-on-plan') ? 1 : 0,
                    'payment_status'  =>  'voucher',
                    'payment_method' => 'voucher',
                    'payment_gateway_type' => 'voucher',
                    'petals' => 0,
                ]);

                //$this->update_radius($user->phone, $planDetails);
                $this->processOrder($planDetails, $user);

                return response()->json([
                    'message' => 'Voucher used',
                    'status' => true,
                    'locationId' => $locationId,
                    'planId' => $planId,
                    'order' => $wifiOrder,
                    'match' => true,
                ], 200);
            } // For Using Reserved Voucher
            else if ($voucher->status == 'reserved' && $voucher->expiry_date >= today()->format('Y-m-d')) {

                if ($voucher->allocated_to_user == true && $voucher->allocated_phone_number == Auth::user()->phone) {
                    $voucher->used_on = Carbon::today();
                    $voucher->user_id = Auth::user()->id;
                    $voucher->location_id = $request->location;
                    $voucher->status = 'redeemed';
                    $voucher->save();

                    $wifiOrder = WiFiOrders::create([
                        'phone' => $user->phone,
                        'internet_plan_id' => $planId,
                        'owner_id' => $locationDetails->owner_id,
                        'status' => 1,
                        'amount' => $planDetails->price,
                        'location_id' => $locationId,
                        'user_id' => Auth::user()->id,
                        'add_on_type' => ($planDetails->add_on_type === 'add-on-plan') ? 1 : 0,
                        'payment_status'  =>  'voucher',
                        'payment_method' => 'voucher',
                        'payment_gateway_type' => 'voucher',
                        'petals' => 0,
                    ]);

                    //$this->update_radius($user->phone, $planDetails);
                    $this->processOrder($planDetails, $user);


                    return response()->json([
                        'message' => 'Voucher used',
                        'status' => true,
                        'locationId' => $locationId,
                        'planId' => $planId,
                        'order' => $wifiOrder,
                        'match' => true,
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Voucher Not Available',
                        'status' => false,
                        'match' => true,
                    ], 200);
                }
            }// Voucher Already Expired
            else if ($voucher->expiry_date <= today()->format('Y-m-d')) {
                return response()->json([
                    'message' => 'Voucher already expired',
                    'status' => false,
                    'match' => true
                ], 200);
            }// Voucher already redeemed
            else if ($voucher->status == 'redeemed') {
                return response()->json([
                    'message' => 'Voucher already redeemed',
                    'status' => false,
                    'match' => true
                ], 200);
            }
        } // Not Found Match of coupon
        else {
            return response()->json([
                'message' => 'Invalid Coupon Code',
                'status' => false,
                'match' => false
            ], 200);
        }
    }

    public function sendVoucherCode(Request $request)
    {
        $phoneNumber = $request->phone;
        $voucherDetails = InternetPlanVouchers::with('internetPlans')->where('id', $request->voucherId)->first();
        $data = json_decode($voucherDetails, true);
        $pdoId = $data['pdo_id'];

        $ssid = PdoNetworkSetting::where('pdo_id', $pdoId)->first();

        $personalSsid = $ssid->essid ?? null;

        $globalSsid = NetworkSettings::select('essid')->first();

        $ssidDetails = $personalSsid != '' ? $personalSsid : $globalSsid->essid;

        $voucherCode = $data['title'];

        $internetPlanName = $data['internet_plans']['name'];

        $phone = '91' . $phoneNumber;

        if($phoneNumber) {
            $userDetails = User::where('phone', $phoneNumber)->first();

            if(!$userDetails) {
                $user['phone'] = $phoneNumber;
                $user['password'] = bcrypt($voucherCode);
                $user['app_password'] = $voucherCode;
                $user['otp_verified_on'] = Carbon::now();
                $user['role'] = 2;
                DB::beginTransaction();
                User::create($user);
                DB::commit();
            }
            $radCheck = DB::connection('mysql2')->table('radcheck')->where('username', $phone)->first();
            if (!$radCheck) {
                $expiration_time = strtotime('today midnight');
                $radius_wifiuser = DB::connection('mysql2')->table('radcheck')
                    ->insert([
                        'username' => $phoneNumber,
                        'value' => $phoneNumber,
                        'attribute' => 'Cleartext-Password',
                        'op' => ':=',
                        'data_limit' => '-1',
                        'session_duration' => '5',
                        'bandwidth' => '20480',
                        'expiration_time' => $expiration_time,
                        'phone_number' => $expiration_time,
                        'name' => $userDetails->first_name ?? null . ' ' . $userDetails->last_name ?? null
                    ]);
                DB::connection('mysql2')->table('radusergroup')->insert([
                    'username' => $phoneNumber,
                    'groupname' => 'pmwani',
                    'priority' => '100',
                ]);
            }
        }

//        $flow_id = '62974583bd6f912e9902dbc9';
        $template_id = '6620f872d6fc054fef638613';
        $sender_id = 'INTPLW';

        $data = array(
            'template_id' => $template_id,
            'sender' => $sender_id,
            'mobiles' => $phone,
            'code' => $voucherCode,
            'ssid' => $ssidDetails,
            'plan' => $internetPlanName
        );

        $postData = json_encode($data);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.msg91.com/api/v5/flow/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postData,

            CURLOPT_HTTPHEADER => [
                "accept:application/json",
                "authkey: 375442AnKUjTl5f624ed867P1",
                "content-type: application/JSON"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return "cURL Error #:" . $err;
        } else {
            $voucherDetails->allocated_to_user = true;
            $voucherDetails->allocated_phone_number = $phoneNumber;
            $voucherDetails->status = 'reserved';
            $voucherDetails->save();
            return $response;
        }
    }


    private function processOrder($order, $user)
    {   Log::info($order);
        $plan_id = $order->id;
        $plan = InternetPlan::find($plan_id);

        $bandwidth = $plan->bandwidth;
        $session_duration = $plan->session_duration;
        $data_limit = $plan->data_limit;
        $expiration_time = $plan->validity * 60;
        $phone = $user->phone;
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

        //Log::info('wifi_orders count -------> '.$wifiOrders);
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

    public function generate_password($length = 4)
    {
        $characters = 'abcdefghijklmnopqrstuvwxy9876543210';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

}
