<?php

namespace App\Http\Controllers;

use App\Models\SessionLog;
use App\Models\WiFiOrders;
use App\Models\UserAcquisition;
use App\Models\PayoutLog;
use App\Models\Location;
use App\Models\PdoaPlan;
use App\Models\User;
use App\Models\InternetPlan;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionLogController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['create','update', 'process_all_logs','process_all_orders']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $session_id = $request->sessionid;
        $username = $request->username;
        $nasid = $request->nasid;
        $phone = substr($username, 0, 10);
        $session_type = '';
        $payment_id = 0;

        $session_log = array();
        $session_log['session_id'] = $session_id;
        $session_log['username'] = $phone;
        $session_log['paymnent_id'] = 0;
        $session_log['plan_price'] = 0;
        $session_log['plan_id'] = 0;
        $session_log['payment_amount'] = 0;
        $session_log['downloads'] = 0;
        $session_log['uploads'] = 0;
        $session_log['session_duration'] = 0;

        $session = DB::connection('mysql2')
            ->table('radacct')
            ->where('acctsessionid', $session_id)
            ->where('username', $username)
            ->where('location_id', $nasid)
            ->first();

        if($session){
            // get last order detail for username
            $start_time = $session->start_date;
            if(strlen($username) == '16'){
                $session_log['session_type'] = 0;
            }else{
                $session_log['session_type'] = 1;
                $last_order = WiFiOrders::where('phone', $phone)
                    ->where('updated_at', '<',$start_time)
                    ->orderBy('id', 'DESC')
                    ->first();

                $session_log['paymnent_id'] = $last_order->id;
                $session_log['plan_id'] = $last_order->internet_plan_id;
                $plan_info = InternetPlan::where('id', $last_order->internet_plan_id)->first();
                $session_log['plan_price'] = $plan_info->price;
                $session_log['payment_amount'] = $last_order->amount;
            }

            $session_log['downloads'] = ceil($session->acctinputoctets/1024);
            $session_log['uploads'] = ceil($session->acctoutputoctets/1024);
            $session_log['session_duration'] = ceil($session->acctsessiontime/60);
            $session_log['session_start_time'] = $session->acctstarttime;
            $session_log['session_update_time'] = $session->acctupdatetime;
            try{
                $session_info = SessionLog::create($session_log);
            }
            catch(Exception $e) {
                return 1;
            }
        }
    }

    public function update(Request $request)
    {
        $session_id = $request->sessionid;
        $username = $request->username;
        $nasid = $request->nasid;
        $phone = substr($username, 0, 10);
        $session_type = '';
        $payment_id = 0;

        $session_log = array();

        $session = DB::connection('mysql2')
            ->table('radacct')
            ->where('acctsessionid', $session_id)
            ->where('username', $username)
            ->where('location_id', $nasid)
            ->first();

        if($session){
            // get last order detail for username

            $session_log['downloads'] = ceil($session->acctinputoctets/1024);
            $session_log['uploads'] = ceil($session->acctoutputoctets/1024);
            $session_log['session_duration'] = ceil($session->acctsessiontime/60);
            $session_log['session_start_time'] = $session->acctstarttime;
            $session_log['session_update_time'] = $session->acctupdatetime;
            $session_info = SessionLog::where('session_id', $session_id)->first();
            if($session_info){
                try {
                    $update_session = $session_info->update($session_log);
                }
                catch(Exception $e) {
                    return 1;
                }
            }
        }
        return 1;

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\SessionLog  $sessionLog
     * @return \Illuminate\Http\Response
     */
    public function show(SessionLog $sessionLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\SessionLog  $sessionLog
     * @return \Illuminate\Http\Response
     */
    public function edit(SessionLog $sessionLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SessionLog  $sessionLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(SessionLog $sessionLog)
    {
        //
    }

    public function process_all_logs()
    {
        $all_sessions = SessionLog::pluck('session_id')->all();
        //dd($all_sessions);
	 /*
        $sessions = DB::connection('mysql2')
            ->table('radacct')
            ->where('wifiadmin.user_session_logs.session_id','!=',)
            ->whereNotNull('location_id')
            ->get();
        */
        $data = array();
        foreach (array_chunk($all_sessions, 5000) as $chunk) { // start add chunk funtion for get data batch wise
        $sessions = DB::connection('mysql2')
            ->table('radacct')
            ->whereNotIn('acctsessionid', $chunk)
            ->whereNotNull('acctstoptime')
            ->select('*')->get();
	   //return compact('sessions');

        // $data = array();
        $i = 0;

        foreach($sessions as $key){
            $session = array();
            $session['id'] = $key->radacctid;
            $session['session_id'] = $key->acctsessionid;
            $session['username'] = $key->username;
            $session['nasid'] = $key->location_id;
            $session['phone'] = substr($key->username, 0, 10);
            $data[$i] = $session;
            $i++;

            $session_id = $key->acctsessionid;
            $username = $key->username;
            $nasid = $key->location_id;
            $phone = substr($username, 0, 10);
            $session_type = '';
            $payment_id = 0;

            $session_log = array();
            $session_log['session_id'] = $session_id;
            $session_log['username'] = $phone;
            $session_log['paymnent_id'] = 0;
            $session_log['plan_price'] = 0;
            $session_log['plan_id'] = 0;
            $session_log['payment_amount'] = 0;
            $session_log['downloads'] = 0;
            $session_log['uploads'] = 0;
            $session_log['session_duration'] = 0;

            $session = DB::connection('mysql2')
                ->table('radacct')
                ->where('acctsessionid', $session_id)
                ->where('username', $username)
                ->where('location_id', $nasid)
                ->first();

            //Log::info($sessions);

            if($session){
                // get last order detail for username
                $start_time = $session->start_date;
                $session_log['location_id'] = $session->location_id;
                $location = Location::where('id', $session->location_id)->first();
                $session_log['location_owner_id'] = $location->owner_id ? $location->owner_id : 0;

                if(strlen($username) == '16'){
                    $session_log['session_type'] = 0;
                }else{
                    $session_log['session_type'] = 1;
                    $last_order = WiFiOrders::where('phone', $phone)
                        ->where('created_at', '<',$start_time)
                        ->where('status', '1')
                        ->where('data_used', false)
                        ->orderBy('id', 'ASC')
                        ->first();

                    if($last_order){
                        // $order_exists = 1;

                        $session_log['paymnent_id'] = $last_order->id;
                        $session_log['plan_id'] = $last_order->internet_plan_id;
                        $plan_info = InternetPlan::where('id', $last_order->internet_plan_id)->first();
                        $session_log['plan_price'] = $plan_info->price;
                        $session_log['payment_amount'] = $last_order->amount;
                        $session_log['plan_data'] = $plan_info->data_available;

                        $update_radacct = DB::connection('mysql2')
                        ->table('radacct')
                        ->where('acctsessionid',$session->acctsessionid)
                        ->update(['payment_id'=>$last_order->id]);

                        // Call another function to perform additional operations
                        $this->wifiOdersUpdate();
                    }
                }

                $session_log['downloads'] = ceil($session->acctinputoctets/1024);
                $session_log['uploads'] = ceil($session->acctoutputoctets/1024);
                $session_log['session_duration'] = ceil($session->acctsessiontime/60);
                $session_log['session_start_time'] = $session->acctstarttime;
                $session_log['session_update_time'] = $session->acctupdatetime;

                try{
                    $session_info = SessionLog::create($session_log);
                }
                catch(Exception $e) {
                    return 1;
                }
            }
        }
        $session_logs = SessionLog::get();
        } // end chunk
        return $data;
        //return response()->json([
          //  'message' => 'WiFi Sessions',
            //'data' => $data,
           // 'session_logs' => $session_logs
       // ], 200);
    }

    public function process_all_orders()
    {
        $today = date("Y-m-d");
        $now_time = date("Y-m-d H:i:s");
        $orders = WiFiOrders::where('status','1')->where('payout_status','0')->where('plan_expired', '0')->get();

        foreach($orders as $key){

        	$plan_id = $key->internet_plan_id;
        	$create_time = $endTime = Carbon::parse($key->created_at);
            $expiration_date = $create_time->addSeconds(InternetPlan::where('id',$plan_id)->first()->session_duration*60);
            $order['expiration_time'] = $expiration_date;
            if($expiration_date < $now_time){
                $update_order = WiFiOrders::where('id',$key->id)->update(['plan_expired'=> 1]);
            }
        }

	    /*$orders = WiFiOrders::where('status','1')
            ->where('payout_status','0')
            ->where('plan_expired', '1')
            ->where('payment_method', '!=', 'voucher')
            ->where('payment_gateway_type', '!=', 'pdo')
            ->get();*/
        $orders->filter(function ($order) {
            return $order->payment_method != 'voucher' &&
                !is_null($order->payment_gateway_type) &&
                $order->payment_gateway_type == 'default';
        });

        /*$orders = WiFiOrders::where('status', '1')
            ->where('payout_status', '0')
            ->where('plan_expired', '0')
            ->where('payment_method', '!=', 'voucher')
            ->where(function ($query) {
                $query->whereNull('payment_gateway_type')
                    ->orWhere('payment_gateway_type', 'default');
            })->get();*/

        // return $orders;

        $orders = $orders->filter(function ($order) {
            return $order->payment_method != 'voucher' &&
             !is_null($order->payment_gateway_type) &&
             $order->payment_gateway_type == 'default';
        });


        $data = array();
        $i = 0;
        $payouts = array();

        foreach($orders as $key){
            if(is_null($key->expiration_date) || ($key->expiration_date < $today)){
                $order = array();
                $order['id'] = $key->id;
                $order['username'] = $key->phone;
                $order['amount'] = $key->amount;
                $order['owner_id'] = $key->owner_id;
                $order['plan_id'] = $plan_id = $key->internet_plan_id;
                $order['created_at'] = $key->created_at;
                $internet_plan = InternetPlan::where('id',$plan_id)->first();
                // $order['amount_for_usage_distribution'] = $key->amount_for_usage_distribution;
                $order['session_duration'] = $internet_plan->session_duration;
                //$location_info;
                //$owner_pdo_info;

                $user = User::where('phone',$key->phone)->first();
                $owner = User::where('id',$key->owner_id)->first();
                $gst_amount = $order['amount']*env('GST_RATE')/100;
                $payment_gateway_charges = $order['amount']*env('PAYMNENT_GW_RATE')/100;
                $amount_after_gst_and_gw = $order['amount']-$gst_amount-$payment_gateway_charges;
                $location_owner_id = $order['owner_id'];
                $pdo_distributor = 0;
                $acquisition_commission = 0;
                $amount_for_usage_distribution = 0;
                $distributor_commission = 0;
                $pdoa_commission = 0;
                $location_id = $key->location_id;

                if(is_null($location_owner_id) || is_null($location_id) || ($location_id == 0)){
                    $acquisition_commission = 0;
                    $amount_for_usage_distribution = $amount_after_gst_and_gw;
                }else{
                    $location_info = Location::where('id', $key->location_id)->first();
                    $pdo_plan_id = $owner->pdo_type;
                    $pdo_distributor = $owner->distributor;
                    $owner_pdo_info = PdoaPlan::where('id',$pdo_plan_id)->first();

                    if ($location_info && $location_info->owner) {
                        // $owner = $location_info->owner;
                        // $payouts = [];

                        if ($user && $owner) {
                            $parent_id = '';
                            $acquisition = UserAcquisition::where('user_id', $user->id)->first();

                            if (!$acquisition) {
                                UserAcquisition::create(['user_id' => $user->id, 'owner_id' => $owner->id]);
                                $parent_id = $owner->id;
                            } else {
                                $parent_id = $acquisition->owner_id;
                            }
                            $acquisition_percentage = $owner_pdo_info->acquisition_commission;
                            $acquisition_commission = round($amount_after_gst_and_gw*$acquisition_percentage/100,2);
                            if($acquisition_commission > 0){
                                $acquisition_payout = array();
                                $acquisition_payout['payout_calculation_date'] = $today;
                                $acquisition_payout['pdo_owner_id'] = $parent_id;
                                $acquisition_payout['payout_amount'] = $acquisition_commission;
                                $acquisition_payout['plan_amount'] = $order['amount'];
                                $acquisition_payout['payout_type'] = 'Acquisition Commission';
                                $acquisition_payout_created = PayoutLog::create($acquisition_payout);

                                //Update wifi order payouts id
                                $update_payoutId = WiFiOrders::where('id',$order['id'])->update(['payoutsId' => $acquisition_payout_created->id]);
                            }
                        }
                        $amount_for_usage_distribution = $amount_after_gst_and_gw - $acquisition_commission;
                    }
                }
                // Here need to update payout log id
                // $amount_for_usage_distribution = $amount_after_gst_and_gw - $distributor_commission - $pdo_commission;
                $update_order = WiFiOrders::where('id',$order['id'])->update(['amount_for_usage_distribution' => $amount_for_usage_distribution]);
                $plan_id = $key->internet_plan_id;
                $create_time = $endTime = Carbon::parse($key->created_at);
                $expiration_date = $create_time->addSeconds(InternetPlan::where('id',$plan_id)->first()->session_duration*60);
                $order['expiration_time'] = $expiration_date;


                $location = Location::where('id', $key->location_id)->first();
                $commission_percentage = 0;
                $owner_id = 0;
                $owner_pdo_plan_id = 0;

                if($location){
                    // continue;
                    $owner_id = $location->owner_id;
                    $owner_pdo_plan_id = User::where('id', $owner_id)->first()->pdo_type;
                    $commission_percentage = PdoaPlan::where('id',$owner_pdo_plan_id)->first()->commission;
                }
                // Here we can update payout log id
                $sessions = SessionLog::where('paymnent_id', $key->id)->get();
                $order['data_usage'] = SessionLog::where('paymnent_id', $key->id)->sum('downloads') + SessionLog::where('paymnent_id', $key->id)->sum('uploads');

                $ii = 0;
                foreach($sessions as $session){
                    if($order['data_usage'] == 0){
                        continue;
                    }
                    $session_location = Location::where('id', $session->location_id)->first();
                    $session_location_owner = User::where('id',$session->location_owner_id)->first();
                    // $session_location_distributor = $session_location_owner->distributor; //old code

                    $session_location_distributor = $session_location_owner->is_parentId;
                    $session_owner_plan = PdoaPlan::where('id',$session_location_owner->pdo_type)->first();

                    $getUser = User::where('id',$session_location_distributor)->first();
                    $subdistributor_commission = 0;

                    if($getUser) {
                        if($getUser->distributor_type == '2' || $getUser->distributor_type == '3') {
                            $subdistributor_commission = $getUser->sub_distributor_comission; // SubDistributor comission 5RS
                        }
                    }
                    $plan_usage_share = ($session->downloads+$session->uploads)/$order['data_usage'];
                    $session->plan_usage_share = ($plan_usage_share*100);

                    $pdo_commission_percentage = $session_owner_plan->commission;
                    $distributor_commission_percentage = $session_owner_plan->distributor_commission - $subdistributor_commission;
                    $subdistributor_commission_percentage = $subdistributor_commission;

                    $pdoa_commission_percentage = 100 - $distributor_commission_percentage - $pdo_commission_percentage - $subdistributor_commission;

                    $session->pdo_commission = round(($plan_usage_share*$amount_for_usage_distribution*$pdo_commission_percentage/100),2);
                    $session->pdoa_commission = round(($plan_usage_share*$amount_for_usage_distribution*$pdoa_commission_percentage/100),2);
                    $session->distributor_commission = round(($plan_usage_share*$amount_for_usage_distribution*$distributor_commission_percentage/100),2);
                    $session->subdistributor_commission = round(($plan_usage_share*$amount_for_usage_distribution*$subdistributor_commission_percentage/100),2);

                    $update_session = SessionLog::where('id', $session->id)->update([
                        'payout_data_ratio' => $session->plan_usage_share,
                        'pdo_payout_amount' => round($session->pdo_commission,2),
                        'pdoa_payout_amount' => round($session->pdoa_commission,2),
                        'distributor_payout_amount' => round($session->distributor_commission,2),
                        'subdistributor_payout_amount' => round($session->subdistributor_commission,2),
                        'total_data_usage' => $order['data_usage']
                        ]
                    );

                    // $payouts['sessions'][] = $session->id;
                    // $payouts['wiFiOrderId'][] = $order['id'];

                    ## Update RADACCT table with payout status and payout amount
                    $rad_session = DB::connection('mysql2')
                    ->table('radacct')
                    ->where('acctsessionid', $session->session_id)
                    ->update(['pdo_payout_amount' => round($session->pdo_commission,2),
                        'pdoa_payout_amount' => round($session->pdoa_commission,2),
                        'distributor_payout_amount' => round($session->distributor_commission,2),
                        'subdistributor_payout_amount' => round($session->subdistributor_commission,2), // manually added field in DB
                        'payout_status' => 1]);

                    $pdo_owner_id = $session->location_owner_id;
                    $pdo_payout = array();
                    $distributor_payout = array();
                    $pdoa_payout = array();
                    $pdo_payout['payout_amount'] = 0.00;
                    $distributor_payout['payout_amount'] = 0.00;
                    $pdoa_payout['payout_amount'] = 0.00;

                    try{
                        if(isset($payouts[$session->location_owner_id])){
                            $pdo_payout  = $payouts[$session->location_owner_id];
                        }
                    }
                    catch (Exception $e) {
                        $payout = array();
                    }

                    try{
                        if(isset($payouts[$session_location_distributor])){
                            $distributor_payout  = $payouts[$session_location_distributor];
                        }
                    }
                    catch (Exception $e) {
                        $payout = array();
                    }

                    try{
                        if(isset($payouts[1])){
                            $pdoa_payout  = $payouts[1];
                        }
                    }
                    catch (Exception $e) {
                        $payout = array();
                    }

                    //PDO ID Data
                    $pdo_payout['payout_date'] = $today;
                    $pdo_payout['payout_amount'] = $pdo_payout['payout_amount'] + $session->pdo_commission;
                    $pdo_payout['pdo_owner_id'] = $session->location_owner_id;

                    // Distributor
                    $distributor_payout['pdo_owner_id'] = $session_location_distributor; // 145, 1
                    $distributor_payout['payout_amount'] = $distributor_payout['payout_amount'] + $session->distributor_commission;

                    // Sub Distributor
                    $distributor_payout['pdo_owner_id'] = $session_location_distributor; // 145, 1
                    $distributor_payout['payout_amount'] = $distributor_payout['payout_amount'] + $session->distributor_commission;

                    // PDOA
                    // $pdoa_payout['payout_amount'] = $pdoa_payout['payout_amount'] + $session->pdoa_commission;
                    // $payout['payout_amount'] = $commission + $session->pdo_commission;
                    // $pdoa_payout['pdo_owner_id'] = 1;
                    // $payouts[1]  = $pdoa_payout;
                    $pdoa_payout['payout_amount'] = $pdoa_payout['payout_amount'] + $session->pdoa_commission;
                    $pdoa_payout['pdo_owner_id'] = 1;
                    $payouts[1]  = $pdoa_payout;
                    $payouts[1]['session_id'][]  = $session->id;
                    $payouts[1]['wiFiOrderId'][]  = $order['id'];

                    $payouts[$session->location_owner_id] = $pdo_payout;
                    $payouts[$session->location_owner_id]['session_id'][]  = $session->id;
                    $payouts[$session->location_owner_id]['wiFiOrderId'][]  = $order['id'];

                    if($session_location_distributor !== 1) {
                        $payouts[$session_location_distributor] = $distributor_payout;
                        $payouts[$session_location_distributor]['session_id'][]  = $session->id;
                        $payouts[$session_location_distributor]['wiFiOrderId'][]  = $order['id'];
                    }
                }
                $order['sessions'] = $sessions;

                $update_order = WiFiOrders::where('id',$order['id'])->update(['payout_status'=>'1', 'payout_calculation_date' => $today]);
                $i++;
            }
        }
        // return $payouts;//_logged;


        // print_r($payouts);
        $payout_logged = array();
        $i = 0;

        foreach($payouts as $payout){
            $payout_info = array();
            $payout_info['payout_calculation_date'] = $today;
            // $payout_info['pdo_owner_id'] = 90;
            $payout_info['pdo_owner_id'] = $payout['pdo_owner_id'];
            $payout_info['payout_amount'] = round($payout['payout_amount'],8);
            $payout_info['payout_type'] = 'Usage Commission';

            // $payout_info['payout_calculation_date'] = $payout['payout_amount'];
            $payout_created = PayoutLog::create($payout_info);
            $payout_logged[$i] = $payout_info;

            // Here update payout log id in session log and wifi order table
            foreach($payout['wiFiOrderId'] as $wiFiOrderId) {
                $update_wiFiOrderId = WiFiOrders::where('id',$wiFiOrderId)->update(['payoutsId'=>$payout_created->id]);
            }
            foreach($payout['session_id'] as $session) {
                $update_session = SessionLog::where('id', $session)->update(['payoutsId'=>$payout_created->id]);
            }
            $i++;
        }
        // print_r($payout_logged);
        return $payout_logged;
    }

    function order_session_log($order_id)
    {
        if(Auth::user()->isPDO()) {
            $WifiOrders = DB::table('wi_fi_orders')
                ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                ->where('wi_fi_orders.id', $order_id)
                ->where('locations.owner_id', Auth::user()->id)
                ->first();

            if($WifiOrders) {
                $sessions = SessionLog::where('paymnent_id', $order_id)
                    ->join('users', 'user_session_logs.location_owner_id', '=', 'users.id')
                    ->where('user_session_logs.location_owner_id', Auth::user()->id)
                    ->get();

                $order = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->select('wi_fi_orders.*', 'wi_fi_orders.created_at as created_at',DB::raw("CONCAT(SUBSTRING(wi_fi_orders.phone, 1, 4), LPAD('', LENGTH(wi_fi_orders.phone) - 6, '*'), SUBSTRING(wi_fi_orders.phone, -2)) as phone"))
                    ->where('locations.owner_id', Auth::user()->id)
                    ->where('wi_fi_orders.id', $order_id)->first();

                return response()->json([
                    'status' => 'success',
                    'message' => 'WiFi user details retrived Successfully',
                    'sessions' => $sessions,
                    'order' => $order
                ], 201);
            } else {
                return response()->json([
                    'status' => 'failure',
                    'message' => 'You canont access this Order',
                    'sessions' => null,
                    'order' => null
                ], 201);
            }


        } else {
            $sessions = SessionLog::where('paymnent_id', $order_id)
                ->join('users', 'user_session_logs.location_owner_id', '=', 'users.id')
                ->get();
            $order = WiFiOrders::where('id', $order_id)->first();
            // return $sessions;
            return response()->json([
                'status' => 'success',
                'message' => 'WiFi user details retrived Successfully',
                'sessions' => $sessions,
                'order' => $order
            ], 201);
        }
    }

    function pdo_session_log(){
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        if($user->role == 0){
            $sessions = SessionLog::where('id', '>', 1000)->get();
        }else{
            $owner_id = $user->id;
            $sessions = SessionLog::select('username','session_start_time','session_update_time','session_type','session_duration','downloads','uploads','plan_id','plan_price','plan_duration','paymnent_id','created_at','updated_at','payment_amount','session_id','payout_ratio','payout_session_duration_ratio','payout_data_ratio','payout_amount','plan_data','location_id','location_owner_id')->where('location_owner_id', $owner_id)->get();

        }
        foreach($sessions as &$session){
            $temp_array = $session;
            $temp_array['username'] = substr($session['username'], 0, 2) . "****" . substr($session['username'], 7, 2);
            $session = $temp_array;
        }
        unset($session);
        return $sessions;
    }

    protected function wifiOdersUpdate()
    {

        //get all unused orders
        $wifiOrders = WiFiOrders::where('status', '1')->where('data_used', false)->orderBy('created_at', 'ASC')->get();

        $wifiOrdersExpireds = WiFiOrders::where('status', '1')->where('plan_expired', true)->where('data_used', false)->orderBy('created_at', 'ASC')->get();
        if ($wifiOrdersExpireds) {
            foreach ($wifiOrdersExpireds as $wifiOrdersExpired) {
                $wifiOrdersExpired->data_used = true;
                $wifiOrdersExpired->save();
            }
        }
        //if empty return 1
        if ($wifiOrders->isEmpty()) {
            return 1 ;
        }
        //if not empty

        foreach ($wifiOrders as $wifiOrder) {

            // Check if user exists in radcheck table
            $radCheckUser = DB::connection('mysql2')->table('radcheck')->where('username', $wifiOrder->phone)->first();
            // Retrieve the latest record based on created_at column
            if (!$radCheckUser) {
                continue;
            }
            // Get current timestamp
            $now = date('d/m/Y');

            // Get data usage information
            $radAcctSession = DB::connection('mysql2')->table('radacct')
                ->select(
                    'username',
                    DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                    DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                )
                ->where('username', $wifiOrder->phone);

            if (!empty($radCheckUser->plan_start_time)) {
                $radAcctSession->where('start_date', '>=', $radCheckUser->plan_start_time);
            }

            $radAcctSession = $radAcctSession->get();

            // If no data usage information found, continue to next order
            if (!$radAcctSession) {
                continue;
            }
            // Calculate total data used and assigned data limit
            if ($radAcctSession) {
                $downloads = 0;
                $uploads = 0;
                foreach ($radAcctSession as $session) {
                    // Access elements of each session
                    $downloads += $session->downloads;
                    $uploads += $session->uploads;
                    // Do something with the data...
                }
                $totalUsedData = $downloads + $uploads;
                $totalAssignData = $radCheckUser->data_limit;
                $planExpiration = date('d/m/Y', $radCheckUser->expiration_time);

                //Log::info('internet_validity-----> '.$planExpiration);
                //Log::info('total used data--------> ' . $totalUsedData .' MB');
                //Log::info('total assign data--------> ' . $totalAssignData .' MB');
                //Log::info('today_date '.$now);

                // Check if data limit exceeded

                if ($totalAssignData <= $totalUsedData || $planExpiration <= $now) {
                    // Mark WiFi order as data used if plan is not expired
                    if ($planExpiration >= $now) {

                        $wifiOrdersAddOnDatas = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)
                            ->where('add_on_type', true)->orderBy('created_at', 'ASC')->first();
                        //Log::info('wifi order add on data phone: ' . $wifiOrder->phone);

                        if ($wifiOrdersAddOnDatas != null) {
                            // Retrieve data limit from related internet plan
                            $internetPlanAddOnData = InternetPlan::find($wifiOrdersAddOnDatas->internet_plan_id);
                            $internetPlanAddOnDataLimit = $internetPlanAddOnData->data_limit;

                            //Log::info('wifi order are mark used save success ' . $wifiOrder);
                            // Update data limit and mark order as data used
                            DB::connection('mysql2')->table('radcheck')->where('username', $wifiOrdersAddOnDatas->phone)->update([
                                'data_limit' => $internetPlanAddOnDataLimit
                            ]);
                           /* $wifiOrdersAddOnDatas->data_used = true;
                            $wifiOrdersAddOnDatas->save();*/
                            $wifiOrder->data_used = true;
                            $wifiOrder->save();

                        } else {
                            $wifiOrder->data_used = true;
                            $wifiOrder->save();
                            // Mark WiFi order as data used because plan has expired
                            $defaultWifiOrders = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)->where('add_on_type', false)->orderBy('created_at', 'ASC')->first();
                            if ($defaultWifiOrders) {
                                //carry forward Data
                                $availableData = $totalAssignData - $totalUsedData ;
                                //Log::info('carry forward data add ' .$availableData);
                                $internetPlanAddOnData = InternetPlan::find($defaultWifiOrders->internet_plan_id);
                                $bandwidth = $internetPlanAddOnData->bandwidth;
                                $session_duration = $internetPlanAddOnData->session_duration;
                                $data_limit = $internetPlanAddOnData->data_limit + $availableData;
                                $expiration_time = $internetPlanAddOnData->validity * 60;
                                $session_duration_window = 0;

                                $expiration_time = time() + $expiration_time;
                                // $plan_start_time = $radWiFiUser->plan_start_time;
                                DB::connection('mysql2')->table('radcheck')->where('username', $defaultWifiOrders->phone)->update([
                                    'expiration_time' => $expiration_time,
                                    'bandwidth' => $bandwidth,
                                    'session_duration' => $session_duration,
                                    'data_limit' => $data_limit,
                                    'session_duration_window' => $session_duration_window,
                                    'plan_start_time' => now()
                                ]);
                                //Log::info( 'WiFi order marked as data used.');
                            }
                        }
                    } else {
                        $wifiOrder->data_used = true;
                        $wifiOrder->save();
                        // Mark WiFi order as data used because plan has expired
                        // Handle expired add-on data
                        $wifiOrdersAddOnDataExpireds = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)
                            ->where('add_on_type', true)->orderBy('created_at', 'ASC')->get();
                        if ($wifiOrdersAddOnDataExpireds != null) {
                            foreach ($wifiOrdersAddOnDataExpireds as $wifiOrdersAddOnDataExpired) {

                                if ($now >= $planExpiration) {

                                    //Log::info('Add-on data expired for phone: ' . $wifiOrdersAddOnDataExpired->phone);
                                    $wifiOrdersAddOnDataExpired->data_used = true;
                                    $wifiOrdersAddOnDataExpired->save();
                                }
                                //Log::info('wifi order are mark used save success ' . $wifiOrdersAddOnDataExpired);
                            }
                            //Log::info('wifi order add on data are mark used save success ');
                        }
                        /* $defaultWifiOrders = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)->orderBy('created_at', 'ASC')->first();
                         //Default WiFi order add data
                         if ($defaultWifiOrders != null) {
                             //carry forward Data
                             $carryForwardData = WiFiOrders::where('status', '1')->where('phone', $session->username)->where('add_on_type',false)->orderBy('created_at', 'ASC')->first();
                             $availableData = 0;
                             if($carryForwardData != null) {
                                 $availableData = $totalAssignData - $totalUsedData ;
                                 Log::info('carry forward data add ' .$availableData);
                             }
                             $internetPlanAddOnData = InternetPlan::find($defaultWifiOrders->internet_plan_id);
                             $bandwidth = $internetPlanAddOnData->bandwidth;
                             $session_duration = $internetPlanAddOnData->session_duration;
                             $data_limit = $internetPlanAddOnData->data_limit + $availableData ?? 0;
                             $expiration_time = $internetPlanAddOnData->validity * 60;
                             $session_duration_window = 0;

                             $expiration_time = time() + $expiration_time;
                             // $plan_start_time = $radWiFiUser->plan_start_time;
                             DB::connection('mysql2')->table('radcheck')->where('username', $defaultWifiOrders->phone)->update([
                                 'expiration_time' => $expiration_time,
                                 'bandwidth' => $bandwidth,
                                 'session_duration' => $session_duration,
                                 'data_limit' => $data_limit,
                                 'session_duration_window' => $session_duration_window,
                                 'plan_start_time' => now()
                             ]);
                             // Mark the default WiFi order as data used and save changes
                             Log::info('WiFi order marked as data used.');
                         }*/
                    }

                } else{
                    // Calculate available data and return response
                    $availableData = $totalAssignData - $totalUsedData ;
                    //Log::info('you have available data ' .$availableData);
                }
            } else {
                return response()->json(['message' => 'No radacct user found for payment id: ' . $wifiOrder->id], 200);
            }
        }
    }
}
