<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Models;
use App\Models\Router;
use App\Models\WiFiOrders;
use App\Models\WiFiStatus;
use App\Models\WiFiUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Log;


class WifiSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => ['forCustomer']
        ]);
    }

    public function index_old(Request $request)
    {
        $locationIds = Location::query()->mine()->pluck('id')->toArray();
        $db1Name = config('database.connections.mysql.database');

        $sessions = DB::connection('mysql2')
            ->table('radacct')
            ->whereIn('location_id', $locationIds)
            ->whereNotNull('radcheck.username')
            ->leftJoin($db1Name . '.locations', 'radacct.location_id', '=', 'locations.id')
            ->leftJoin('radcheck', 'radacct.username', '=', 'radcheck.username')
            ->select('radcheck.username as username','radacct.payment_id as payment_id', 'radacctid', 'callingstationid as usermac', 'calledstationid','radcheck.phone_number as phone_number', 'radcheck.name as name', 'acctsessionid', 'start_date', 'acctstoptime', 'acctinputoctets', 'acctoutputoctets','acctsessiontime', 'locations.name as location_name', 'locations.id as location_id', 'pdo_payout_amount as payout_amount', 'payout_status')
            ->distinct()
            ->orderByDesc('start_date')
            ->get();

        return $sessions;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $locationIds = Location::query()->mine()->pluck('id')->toArray();
        $db1Name = config('database.connections.mysql.database');

        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $start = $request->get("start", 0);
        $rowperpage = $request->get("length", 10);
        $skip = $start;
        $take = $rowperpage;
        $columnIndex = $request->get('order')[0]['column'];
        $columnName = $request->get('columns')[$columnIndex]['data'];
        $columnSortOrder = $request->get('order')[0]['dir'];
        $searchValue = $request->get('search')['value'];
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        $formattedStartDate = $formattedEndDate = null;

        if (!empty($startDate) && !empty($endDate)) {
            $startDateTimestamp = $startDate / 1000;
            $endDateTimestamp = $endDate / 1000;

            $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
            $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

            $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
            $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');
        }
        
        $query = DB::connection('mysql2')
            ->table('radacct')
            ->whereIn('location_id', $locationIds)
            ->select('radacct.*')
            // ->leftJoin('radcheck', 'radacct.username', '=', 'radcheck.username');
            ->leftJoin('radcheck', function ($join) {
                $join->on(DB::raw('radacct.username COLLATE utf8mb3_general_ci'), '=', 'radcheck.username');
            });
        if (!empty($formattedStartDate) && !empty($formattedEndDate)) {
            $query->whereBetween('radacct.acctstarttime', [$formattedStartDate, $formattedEndDate]);
        }

        if (!empty($searchValue)) {
            $query->where(function ($subquery) use ($searchValue) {
                $subquery->where('radcheck.username', 'like', '%' . $searchValue . '%')
                    ->orWhere('radacct.calledstationid', 'like', '%' . $searchValue . '%')
                    ->orWhere('radacct.callingstationid', 'like', '%' . $searchValue . '%')
                    ->orWhere('radacct.acctsessionid', 'like', '%' . $searchValue . '%');
            });
        }
        $totalRecords = $query->count();
        $query->orderBy($columnName, $columnSortOrder);
        $totalRecordsWithFilter = $query->count();

        $sessions = $query->select('radacct.username as username', 'radacct.payment_id as payment_id', 'radacctid', 'callingstationid as usermac', 'calledstationid', 'acctsessionid', 'start_date', 'acctstarttime', 'acctstoptime', 'acctinputoctets', 'acctoutputoctets', 'acctsessiontime', 'pdo_payout_amount as payout_amount', 'radacct.payout_status as payout_status', 'radcheck.phone_number as phone_number', 'location_id', 'payment_id')
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $locationIds = $sessions->pluck('location_id')->toArray();
        $locations = Location::whereIn('id', $locationIds)->select('name as location_name', 'id')->get();

        $orderIds = $sessions->pluck('payment_id')->toArray();
        $orders = WiFiOrders::whereIn('id', $orderIds)->select('id', 'payout_status', 'created_at', 'status', 'payment_status','payment_method')->get();

        $data_arr = $sessions->map(function ($session) use ($locations, $orders, $user) {
            $location = $locations->firstWhere('id', $session->location_id);
            $order = $orders->firstWhere('id', $session->payment_id);

            $name = $session->phone_number ? (($user->isPDO() && strlen($session->phone_number) === 10) ? substr_replace($session->phone_number, str_repeat('*', strlen($session->phone_number) - 6), 4, -2) : $session->username) : ($session->username ? ((Auth::user()->isPDO() && strlen($session->username) === 10) ? substr_replace($session->username, str_repeat('*', strlen($session->username) - 6), 4, -2) : $session->username) : null);

            return (object)array_merge(
                (array)$session,
                [
                    "name" => $name,
                    "username" => $session->username,
                    "usermac" => $session->usermac,
                    "calledstationid" => $session->calledstationid,
                    "location_name" => $location->location_name ?? null,
                    "location_id" => $location->id ?? null,
                    "acctsessionid" => $session->acctsessionid,
                    "acctstarttime" => $session->acctstarttime,
                    "acctstoptime" => $session->acctstoptime,
                    "acctsessiontime" => $session->acctsessiontime,
                    "acctinputoctets" => $session->acctinputoctets,
                    "acctoutputoctets" => $session->acctoutputoctets,
                    "payout_status" => $session->payout_status,
                    "payout_amount" => $session->payout_amount,
                    "radacctid" => $session->radacctid,
                    "order_id" => $order->id ?? null,
                    "status" => $order->status ?? null,
                    "payment_status" => $order->payment_status ?? null,
                    'payment_method' => $order->payment_method ?? null,
                ]
            );
        });

        $response = [
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordsWithFilter,
            "data" => $data_arr,
        ];
        return response()->json($response);
    }

    public function forCustomer(Request $request) {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $phone = $user->phone;
        $macAddresses = WiFiUser::where('phone', $phone)->pluck('mac_address');
        $macAddresses->push($phone);

        $db1Name = config('database.connections.mysql.database');
        /*$sessions = DB::connection('mysql2')
            ->table('radacct')
            ->where('radcheck.username', $phone)
            ->orWhere('radcheck.username', $phone.'--Free')
            ->leftJoin($db1Name . '.locations', 'radacct.location_id', '=', 'locations.id')
            ->leftJoin('radcheck', 'radacct.username', '=', 'radcheck.username')
            ->select('radcheck.username as username', 'callingstationid  as usermac', 'radcheck.phone_number as phone_number', 'radacctid', 'acctsessionid', 'acctstarttime', 'acctstoptime', 'acctinputoctets', 'acctoutputoctets', 'locations.name as location_name', 'locations.id as location_id')
            ->distinct()
            ->orderByDesc('radacctid')->get();

        return $sessions;*/

        $sessions = DB::connection('mysql2')
        ->table('radacct')
            ->leftJoin('radcheck', 'radacct.username', '=', 'radcheck.username')
            ->whereIn('radcheck.username', [$phone, $phone . '--Free'])
            ->select('radcheck.username as username', 'callingstationid as usermac', 'radcheck.phone_number as phone_number', 'radacctid', 'acctsessionid', 'acctstarttime', 'acctstoptime', 'acctinputoctets', 'acctoutputoctets', 'location_id')
            ->orderByDesc('radacctid')
            ->get();

        $locationIds = $sessions->pluck('location_id')->toArray();

        $locations = Location::whereIn('id', $locationIds)->select( 'name', 'id')->get();

        $combinedSessions = $sessions->map(function ($session) use ($locations) {
            $location = $locations->firstWhere('id', $session->location_id);

            return (object)array_merge(
                (array)$session,
                [
                    'location_name' => $location->name ?? null,
                    'location_id' => $location->id ?? null,
                ]
            );
        });

        return $combinedSessions;
    }


    public function statuses(Request $request)
    {
        $routerIds = [];
        if ($request->id) {
            $routerIds = [$request->id];
        }
        if (!$routerIds || count($routerIds) == 0) {
            $routerIds = WiFiStatus::query()->select(['wifi_router_id'])->distinct()->pluck('wifi_router_id');
        }

        $startDate = Carbon::now()->subDay();
        $endDate = Carbon::now();

        $routers = Router::find($routerIds);

        $existingIds = $routers->pluck('id');
        $statuses = WiFiStatus::query()
            ->whereIn('wifi_router_id', $existingIds)
            ->orderBy('created_at')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where('created_at', '>=', $startDate);
                $query->where('created_at', '<=', $endDate);
                return $query;
            })
            ->get();
        return compact('statuses', 'routers', 'startDate', 'endDate');
    }
}
