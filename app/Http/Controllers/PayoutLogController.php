<?php

namespace App\Http\Controllers;
use App\Events\PdoPayoutEvent;
use App\Models\NotificationSettings;
use Illuminate\Support\Facades\Auth;

use App\Models\PayoutLog;
use Illuminate\Http\Request;
use App\Models\WiFiOrders;
use App\Models\SessionLog;
use App\Models\Distributor;
use App\Models\PdoaPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutLogController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['list','update', 'process_all_logs','process_all_orders']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function list()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        // return $user;
        if($user->role == 0){
            return PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
            ->select('payout_logs.id as id','payout_logs.plan_amount as plan_amount', 'payout_logs.payout_type as payout_type', 'users.first_name as first_name','users.last_name as last_name','users.email as email', 'payout_logs.payout_amount as payout_amount','payout_logs.payout_status as payout_status','payout_logs.payout_calculation_date as payout_calculation_date','payout_logs.payout_date as payout_date','payout_logs.created_at as created_at')
            ->orderBy('id', 'desc')
            ->get();
        }else{
            return PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
            ->select('payout_logs.id as id','payout_logs.plan_amount as plan_amount', 'payout_logs.payout_type as payout_type', 'users.first_name as first_name','users.last_name as last_name','users.email as email', 'payout_logs.payout_amount as payout_amount','payout_logs.payout_status as payout_status','payout_logs.payout_calculation_date as payout_calculation_date','payout_logs.payout_date as payout_date','payout_logs.created_at as created_at')
            ->where('users.id',$user->id)
            ->orderBy('id', 'desc')
            ->get();
        }
    }

    public function getTotalPdoData(){
        echo 'Hello';
        exit;
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $accumalationData = PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
            ->where('payout_logs.pdo_owner_id1', $user->id)
            ->get();
        print_r($accumalationData);
        return $accumalationData;
    }

    public function getPayout(Request $request)
     {
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;
        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $userDetail = User::find($user->id);
        $payoutData = PayoutLog::select(DB::raw('SUM(payout_logs.payout_amount) as pdoTotalAmount'))
            ->where('payout_logs.pdo_owner_id', $user->id)
            ->whereBetween('payout_logs.payout_calculation_date',[$formattedStartDate,$formattedEndDate])
            ->groupBy('payout_logs.pdo_owner_id')
            ->get()->toArray();
        
        $accumalationData = PayoutLog::select('payout_logs.payout_calculation_date','payout_logs.payout_amount')
            ->where('payout_logs.pdo_owner_id', $user->id)
            ->whereBetween('payout_logs.payout_calculation_date',[$formattedStartDate,$formattedEndDate])
            ->get();

        $totalPdoData = [];
        $i =0;
        foreach($accumalationData as $accData) {
            
            $setData = [
                $accData->payout_calculation_date,
                number_format($accData->payout_amount,2)
            ];
            $totalPdoData[$i] = $setData;
            $i++;
        }

        $thresholdRemains = 0;
        $pdoUnpaidAmount = 0;
        $pdoPaidAmount = 0;
        $pdoUnpaidAmountThreshold = 0;

        $getPaidAmount = PayoutLog::select(DB::raw('SUM(payout_logs.payout_amount) as pdoPaidAmount'))
            ->where('payout_logs.pdo_owner_id', $user->id)
            ->where('payout_logs.payout_status', 1)
            ->whereBetween('payout_logs.payout_calculation_date',[$formattedStartDate,$formattedEndDate])
            ->groupBy('payout_logs.pdo_owner_id')
            ->first();
        if($getPaidAmount) {
            $pdoPaidAmount = $getPaidAmount->pdoPaidAmount;
        }

        // Threshold Data
        
        $getUnpaidAmount = PayoutLog::select(DB::raw('SUM(payout_logs.payout_amount) as pdoUnpaidAmount'))
            ->where('payout_logs.pdo_owner_id', $user->id)
            ->where('payout_logs.payout_status', 0)
            ->whereBetween('payout_logs.payout_calculation_date',[$formattedStartDate,$formattedEndDate])
            ->groupBy('payout_logs.pdo_owner_id')
            ->first();
        if($getUnpaidAmount) {
            $pdoUnpaidAmount = $getUnpaidAmount->pdoUnpaidAmount;
        }

        $getUnpaidAmountForThreshold = PayoutLog::select(DB::raw('SUM(payout_logs.payout_amount) as pdoUnpaidAmount'))
            ->where('payout_logs.pdo_owner_id', $user->id)
            ->where('payout_logs.payout_status', 0)
            ->whereBetween('payout_logs.payout_calculation_date',[$formattedStartDate,$formattedEndDate])
            ->groupBy('payout_logs.pdo_owner_id')
            ->first();
        if($getUnpaidAmountForThreshold) {
            $pdoUnpaidAmountThreshold = $getUnpaidAmountForThreshold->pdoUnpaidAmount;
        }

        $pdoPlanData = PdoaPlan::where('id',$userDetail->pdo_type)->first();

        if($pdoPlanData) {
            if($pdoPlanData->thresholdLimit != 0) {
                $remainBalance = (100*$pdoUnpaidAmountThreshold)/$pdoPlanData->thresholdLimit;
                if($remainBalance > 100) {
                    $remainBalance = 100;
                }
                $thresholdRemains = number_format($remainBalance,2);
            }
        }
        
        return compact('payoutData', 'totalPdoData','thresholdRemains','pdoUnpaidAmount','pdoPaidAmount');
     }

    public function pdo_list($id)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        // return $user;
        if($user->role == 0){
            $payouts = PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
            ->select('payout_logs.id as id','payout_logs.plan_amount as plan_amount', 'payout_logs.payout_type as payout_type', 'users.first_name as first_name','users.last_name as last_name','users.email as email', 'payout_logs.payout_amount as payout_amount','payout_logs.payout_status as payout_status','payout_logs.payout_calculation_date as payout_calculation_date','payout_logs.payout_date as payout_date',)
            ->where('pdo_owner_id',$id)
            ->get();
            $unpaid_amount = PayoutLog::where('pdo_owner_id',$id)
            ->where('payout_status',0)
            ->sum('payout_amount');
        }else{
            $payouts = PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
            ->select('payout_logs.id as id','payout_logs.plan_amount as plan_amount', 'payout_logs.payout_type as payout_type', 'users.first_name as first_name','users.last_name as last_name','users.email as email', 'payout_logs.payout_amount as payout_amount','payout_logs.payout_status as payout_status','payout_logs.payout_calculation_date as payout_calculation_date','payout_logs.payout_date as payout_date')
            ->where('users.id',$user->id)
            ->orderBy('id', 'desc')
            ->get();
            $unpaid_amount = PayoutLog::where('pdo_owner_id',$user->id)
            ->where('payout_status',0)
            ->sum('payout_amount');
        }

        return compact('payouts', 'unpaid_amount');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function payout_detail($payout_id, Request $request)
    {
        $payout = $sessions = [];
        $payout = PayoutLog::where('id', $payout_id)->first();
        if($payout) {
            // return $payout->payout_calculation_date;
            $wifi_orders = WiFiOrders::select('id')->where('status','1')
            ->where('payout_status','1')
            ->where('payoutsId',$payout_id)
            ->where('payout_calculation_date',$payout->payout_calculation_date)
            ->pluck('id')->toArray();

            // $sessions = SessionLog::whereIn('paymnent_id', $wifi_orders)->where('location_owner_id', $payout->pdo_owner_id)->get();
            // DB::table($db1Name.'.locations')->leftjoin($db2Name.'.radacct as radacct', $db1Name.'.locations.id', '=', $db2Name.'.radacct.location_id')
            $sessions = DB::table('user_session_logs')->leftjoin('locations','locations.id','=','user_session_logs.location_id')
            ->whereIn('user_session_logs.paymnent_id', $wifi_orders)->where('user_session_logs.location_owner_id', $payout->pdo_owner_id)->where('payoutsId',$payout_id)->get();
            foreach($sessions as $session){
                $temp_array = $session;
                // $temp_array->username = substr($session->username, 0, 2) . "****" . substr($session->username, 7, 2);
                    $temp_array->username = substr($session->username, 0, 2) . "****" . substr($session->username, 8, 2);
                $session = $temp_array;
            }
            unset($session);
        }
        return compact('payout', 'sessions');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function payout_proccessed($payout_id, Request $request)
    {
        $today = date("Y-m-d");
        $payout = PayoutLog::where('id', $payout_id)->first();
        $payout->payout_status = 1;
        $payout->payout_date = $today;
        $payout->save();
        $wifi_orders = WiFiOrders::select('id')->where('status','1')
        ->where('payout_status','1')
        ->where('payout_calculation_date',$payout->payout_calculation_date)
        ->pluck('id')->toArray();

        // $sessions = SessionLog::whereIn('paymnent_id', $wifi_orders)->where('location_owner_id', $payout->pdo_owner_id)->get();
        // DB::table($db1Name.'.locations')->leftjoin($db2Name.'.radacct as radacct', $db1Name.'.locations.id', '=', $db2Name.'.radacct.location_id')
        $sessions = DB::table('user_session_logs')->leftjoin('locations','locations.id','=','user_session_logs.location_id')
        ->whereIn('user_session_logs.paymnent_id', $wifi_orders)->where('user_session_logs.location_owner_id', $payout->pdo_owner_id)->get();
        foreach($sessions as &$session){
            $rad_session = DB::connection('mysql2')
                    ->table('radacct')
                    ->where('acctsessionid', $session->session_id)
                    ->update(['payout_status' => 2]);
        }
        // Send email to user if amount is > 0
        if($payout->payout_amount > 0){

        }
        $user = User::where('id',$payout->pdo_owner_id)->first();
        $notification = NotificationSettings::where('pdo_id',$payout->pdo_owner_id)->where('notification_type','payout')
            ->where('frequency','on-event')->first();
        if($notification) {
            event(new PdoPayoutEvent($user,$payout ,$notification));
        } else {
            $notification = $this->notification = null;
            event(new PdoPayoutEvent($user,$payout ,$notification));
        }

        return $payout;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PayoutLog  $payoutLog
     * @return \Illuminate\Http\Response
     */
    public function show(PayoutLog $payoutLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\PayoutLog  $payoutLog
     * @return \Illuminate\Http\Response
     */
    public function edit(PayoutLog $payoutLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PayoutLog  $payoutLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PayoutLog $payoutLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PayoutLog  $payoutLog
     * @return \Illuminate\Http\Response
     */
    public function process_payouts(Request $request)
    {
        $orders = WiFiOrders::where('status','1')->where('payout_status','1')->get();
        foreach($orders as $key){


        }
    }

    public function distributor_list()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $data = array();

        if ($user->isAdmin()) {
           $usersData = User::where('role',3)->orderBy('id', 'DESC')->get();
        } else if($user->distributor_type == 1) { // check master distributor
            $usersData = User::where(['role'=>3, 'is_parentId'=>$user->id])->orderBy('id', 'DESC')->get(); //, 'is_parentId'=>$user->id
        } else {
            $usersData = User::where('id', $user->id)->get();
        }

        if(count($usersData)) {
            foreach ($usersData as $key => $value) {
                $nestedData = array();
                $payouts = PayoutLog::where('pdo_owner_id', $value->id)->get();
                $amt = 0;
                if($payouts) {
                    foreach ($payouts as $key => $payout) {
                        $nestedData['id'] = $payout->id;
                        $nestedData['name'] = $value->first_name .' '. $value->last_name;
                        $nestedData['email'] = $value->email;
                        $nestedData['payout_calculation_date'] = $payout->payout_calculation_date;
                        $nestedData['payout_type'] = $payout->payout_type;
                        $nestedData['payout_status'] = $payout->payout_status;
                        // $nestedData['plan_amount'] = $payout->plan_amount;
                        $nestedData['payout_amount'] = $payout->payout_amount;
                        $nestedData['payout_date'] = $payout->payout_date;
                        $data[] =$nestedData;
                    }
                }
            }
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } else {
            return response()->json([
                'success' => false,
                'data' => NULL
            ]);
        }

        // OLD CODE
        // $user = Auth::user();
        // // return $user;
        // if($user->role == 0){
        //     $payouts = PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
        //     ->select('payout_logs.id as id','payout_logs.plan_amount as plan_amount', 'payout_logs.payout_type as payout_type', 'users.first_name as first_name','users.last_name as last_name','users.email as email', 'payout_logs.payout_amount as payout_amount','payout_logs.payout_status as payout_status','payout_logs.payout_calculation_date as payout_calculation_date','payout_logs.payout_date as payout_date',)
        //     ->where('pdo_owner_id',$id)
        //     ->get();
        //     $unpaid_amount = PayoutLog::where('pdo_owner_id',$id)
        //     ->where('payout_status',0)
        //     ->sum('payout_amount');
        // }else{
        //     $payouts = PayoutLog::leftJoin('users','payout_logs.pdo_owner_id','=','users.id')
        //     ->select('payout_logs.id as id','payout_logs.plan_amount as plan_amount', 'payout_logs.payout_type as payout_type', 'users.first_name as first_name','users.last_name as last_name','users.email as email', 'payout_logs.payout_amount as payout_amount','payout_logs.payout_status as payout_status','payout_logs.payout_calculation_date as payout_calculation_date','payout_logs.payout_date as payout_date')
        //     ->where('users.id',$user->id)
        //     ->orderBy('id', 'desc')
        //     ->get();
        //     $unpaid_amount = PayoutLog::where('pdo_owner_id',$user->id)
        //     ->where('payout_status',0)
        //     ->sum('payout_amount');
        // }

        // return compact('data', 'unpaid_amount');
    }
    function approved(Request $request){
        echo 'Hello';
        echo $request->get('pdo_id');
        // Status update in threshold list 
        // Redirect to payment gateway
    }
}
