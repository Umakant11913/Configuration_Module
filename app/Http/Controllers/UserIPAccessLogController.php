<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Router;
use App\Models\UserIPAccessLog;
use App\Models\KernelLog;
use App\Models\MessageLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;

class UserIPAccessLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index2()
    {
        $IP_logs = UserIPAccessLog::orderBy('id', 'DESC')->get();
    	return $IP_logs;
    }

    public function index(Request $request)
    {
        $defaultDB = config('database.connections.mysql.database');
        $logDB = config('database.connections.mysql3.database');
        
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
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;

        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $totalRecordswithFilter = "";
        $logs = '';
        
        $user = Auth::user();
        $locationArr = Location::select('id')->where('owner_id', $user->id)->get()->toArray();
        $query = UserIPAccessLog::on('mysql3')
        ->leftJoin($defaultDB.'.routers as r', $logDB.'.user_i_p_access_logs.router_id', '=', 'r.id')
        ->leftJoin($defaultDB.'.locations as l', 'l.id', '=', 'r.location_id')
        ->leftJoin($defaultDB.'.users as u', 'u.id', '=', 'l.owner_id')
        ->select(
            $logDB.'.user_i_p_access_logs.*',
            'l.name as location_name',
            'r.name as router_name',
            'r.mac_address',
            'u.first_name',
            'u.last_name',
            'u.email',
            'r.serial_number'
        );
        if($user->role == 1)
        {
            $totalRecords = DB::table($logDB.'.user_i_p_access_logs')->whereIn('location_id',$locationArr)->count();
        }else{
            $totalRecords = DB::table($logDB.'.user_i_p_access_logs')->count();
        }
        $query->where(function ($q) use ($searchValue, $logDB) {
            $q->where($logDB.'.user_i_p_access_logs.username', 'like', "%$searchValue%")
              ->orWhere($logDB.'.user_i_p_access_logs.src_ip', 'like', "%$searchValue%")
              ->orWhere($logDB.'.user_i_p_access_logs.dest_ip', 'like', "%$searchValue%")
              ->orWhere($logDB.'.user_i_p_access_logs.src_port', 'like', "%$searchValue%")
              ->orWhere($logDB.'.user_i_p_access_logs.user_mac_address', 'like', "%$searchValue%")
              ->orWhere($logDB.'.user_i_p_access_logs.dest_port', 'like', "%$searchValue%")
              ->orWhere('r.name', 'like', "%$searchValue%")
              ->orWhere('r.mac_address', 'like', "%$searchValue%")
              ->orWhere('r.serial_number', 'like', "%$searchValue%")
              ->orWhere('l.name', 'like', "%$searchValue%")
              ->orWhere('u.first_name', 'like', "%$searchValue%")
              ->orWhere('u.last_name', 'like', "%$searchValue%")
              ->orWhere('u.email', 'like', "%$searchValue%");
        });
            
        $locations = Location::pluck('name', 'id');
        $routers = Router::pluck('name', 'id');

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween($logDB.'.user_i_p_access_logs.created_at', [$formattedStartDate, $formattedEndDate]);
        }        
        $sortableColumns = [
            'router_name' => 'r.name',
            'router_mac_address' => 'r.mac_address',
            'router_serial_number' => 'r.serial_number',
            'location_name' => 'l.name'
        ];
        $totalRecordswithFilter = $query->select($logDB.'.user_i_p_access_logs.*')->count();
        $query->orderBy($sortableColumns[$columnName] ?? $logDB.'.user_i_p_access_logs.created_at', $columnSortOrder);
        
        $logs = $query->
            take($rowperpage)
                ->skip($start)
                ->get();
                
        $data_arr = array();

        foreach ($logs as $log) {
            $id = $log->id;
            $username = $log->username;
            $srcIP = $log->src_ip;
            $destIP = $log->dest_ip;
            $srcPort = $log->src_port;
            $destport = $log->dest_port;
            $translatedIP = $log->client_device_translated_ip;
            $port = $log->port;
            $createdAt = $log->created_at;
            $updatedAt = $log->updated_at;
            $locationName = $locations[$log->location_id] ?? 'NA';
            $routerName = $routers[$log->router_id] ?? 'NA';
            $userMacAddress = $log->user_mac_address ?? 'NA';
            $routerSerialNo = (isset($log->router))?$log->router->serial_number : "";

            $data_arr[] = array(
                "id" => $id,
                "username" => $username,
                "src_ip" => $srcIP,
                "dest_ip" => $destIP,
                "src_port" => $srcPort,
                "dest_port" => $destport,
                "client_device_translated_ip" => $translatedIP,
                "created_at" => $createdAt,
                "updated_at" => $updatedAt,
                "location_name" =>$locationName,
                "router_name" =>$routerName,
                "router_serial_number" =>$routerSerialNo,
                "user_mac_address" =>$userMacAddress
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

    public function kernellogs(Request $request)
    {
        $defaultDB = config('database.connections.mysql.database');
        $logDB = config('database.connections.mysql3.database');
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');
        $columnname = $request->get('columnname');
        $columnval = $request->get('columnval');
        $columncatval = $request->get('columncatval');

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // Search value
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;

        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $totalRecordswithFilter = "";
        $logs = '';
        
        $user = Auth::user();
        $locationArr = Location::select('id')->where('owner_id', $user->id)->get()->toArray();
        $routerArr = Router::select('id')->where('owner_id', $user->id)->get()->makeHidden(['isOnline'])->toArray();
        if($user->role == 1)
        {
            $totalRecords = DB::table($logDB.'.kernel_logs')->whereIn('router_id',$routerArr)->count(); 
        }else{
            $totalRecords = DB::table($logDB.'.kernel_logs')->count();
        }      
        $query = KernelLog::on('mysql3')
        ->leftJoin($defaultDB.'.routers as r', $logDB.'.kernel_logs.router_id', '=', 'r.id')
        ->leftJoin($defaultDB.'.locations as l', 'l.id', '=', 'r.location_id')
        ->select(
            $logDB.'.kernel_logs.*',
            'l.name as location_name',
            'r.name as router_name',
            'r.mac_address',
            'r.serial_number'
        );
        if(!empty($columnname) && !empty($columnval))
        {
            $query->where(function ($q) use ($columnname, $columnval) {
                if($columnname != 'name' && $columnname != 'serial_number' && $columnname != 'mac_address' && $columnname != 'location_name')
                {
                    $q->where($columnname, 'like', '%' . $columnval . '%');
                }else{
                    if($columnname != 'location_name')
                    {
                        $q->where('r.'.$columnname, 'like', '%'.$columnval.'%');
                    }else{
                        $q->where('l.name', 'like', '%' . $columnval . '%');
                    }
                }
            });            
        }
        if(!empty($searchValue)){
            $query->where(function ($q) use ($searchValue, $logDB) {
                 $q->where($logDB.'.kernel_logs.program', 'like', "%$searchValue%")
                   ->orWhere($logDB.'.kernel_logs.pid', 'like', "%$searchValue%")
                   ->orWhere($logDB.'.kernel_logs.facility', 'like', "%$searchValue%")
                   ->orWhere($logDB.'.kernel_logs.msg', 'like', "%$searchValue%")
                   ->orWhere('r.name', 'like', "%$searchValue%")
                   ->orWhere('r.mac_address', 'like', "%$searchValue%")
                   ->orWhere('r.serial_number', 'like', "%$searchValue%")
                   ->orWhere('l.name', 'like', "%$searchValue%");
             });
         }
        // dd($query->toSql());
        if($user->role == 1)
        {
            $query->whereIn($logDB.'.kernel_logs.router_id',$routerArr);
        }        
        if(!empty($columncatval))
        {
            $query->where($logDB.'.kernel_logs.facility', 'like', '%' . $columncatval . '%');    
        }
        $locations = Location::pluck('name', 'id');
        $routers = Router::pluck('name', 'id');
        
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween($logDB.'.kernel_logs.created_at', [$formattedStartDate, $formattedEndDate]);
        }
        $sortableColumns = [
            'router_name' => 'r.name',
            'router_mac_address' => 'r.mac_address',
            'router_serial_number' => 'r.serial_number',
            'location_name' => 'l.name',
            'facility' => $logDB.'.kernel_logs.facility',
            'program' => $logDB.'.kernel_logs.program',
            'pid' => $logDB.'.kernel_logs.pid',
        ];
        
        $query->orderBy($sortableColumns[$columnName] ?? $logDB.'.kernel_logs.created_at', $columnSortOrder);
        $totalRecordswithFilter = $query->count();
        $logs = $query->
            take($rowperpage)
                ->skip($start)
                ->get();
                
        // $totalRecordswithFilter = $query->select($logDB.'.kernel_logs.*')->count();
        // $totalRecordswithFilter = $totalRecords;
        $data_arr = array();
        foreach ($logs as $log) {
            $id = $log->id;
            $msg = $log->msg;
            $pid = $log->pid;
            $program = $log->program;
            $facility = $log->facility;
            $createdAt = $log->created_at;
            $updatedAt = $log->updated_at;
            // $routerName = $routers[$log->router_id] ?? 'NA';
            // $routerSerialNo = (isset($log->router))?$log->router->serial_number : "";
            // $routermac_address = (isset($log->router))?$log->router->mac_address : "";
            // $routerLocationName = (isset($log->router->location))?$log->router->location->name : "";
            $routerName = $log->router_name;//$routers[$log->router_id] ?? 'NA';
            $routerSerialNo = $log->serial_number;//(isset($log->router))?$log->router->serial_number : "";
            $routermac_address = $log->mac_address;//(isset($log->router))?$log->router->mac_address : "";
            $routerLocationName = $log->location_name;//(isset($log->router->location))?$log->router->location->name : "";


            $data_arr[] = array(
                "id" => $id,
                "msg" => $msg,
                "program" => $program,
                "facility" => $facility,
                "pid" => $pid,
                "created_at" => $createdAt,
                "updated_at" => $updatedAt,
                "router_serial_number" =>$routerSerialNo,
                "location_name" => $routerLocationName,
                "router_mac_address" => $routermac_address,        
                "router_name" =>$routerName
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

    public function msglogs(Request $request)
    {
        $defaultDB = config('database.connections.mysql.database');
        $logDB = config('database.connections.mysql3.database');
        
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
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;
        $columnname = $request->get('columnname');
        $columnval = $request->get('columnval');
        $columncatval = $request->get('columncatval');

        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $totalRecordswithFilter = "";
        $logs = '';
        
        $user = Auth::user();
        $locationArr = Location::select('id')->where('owner_id', $user->id)->get()->toArray();
        $routerArr = Router::select('id')->where('owner_id', $user->id)->get()->makeHidden(['isOnline'])->toArray();
        if($user->role == 1)
        {
            $totalRecords = DB::table($logDB.'.message_logs')->whereIn('router_id',$routerArr)->count();        
        }else{
            $totalRecords = DB::table($logDB.'.message_logs')->count();
        }
        // dd($totalRecords);
        $query = MessageLog::on('mysql3')
        ->leftJoin($defaultDB.'.routers as r', $logDB.'.message_logs.router_id', '=', 'r.id')
        ->leftJoin($defaultDB.'.locations as l', 'l.id', '=', 'r.location_id')
        ->select(
            $logDB.'.message_logs.*',
            'l.name as location_name',
            'r.name as router_name',
            'r.mac_address',
            'r.serial_number'
        );
        if(!empty($columnname) && !empty($columnval))
        {
            $query->where(function ($q) use ($columnname, $columnval) {
                if($columnname != 'name' && $columnname != 'serial_number' && $columnname != 'mac_address' && $columnname != 'location_name')
                {
                    $q->where($columnname, 'like', '%' . $columnval . '%');
                }else{
                    if($columnname != 'location_name')
                    {
                        $q->where('r.'.$columnname, 'like', '%'.$columnval.'%');
                    }else{
                        $q->where('l.name', 'like', '%' . $columnval . '%');
                    }
                }
            });            
            
        }
        if(!empty($searchValue)){
            $query->where(function ($q) use ($searchValue, $logDB) {
                 $q->where($logDB.'.message_logs.program', 'like', "%$searchValue%")
                   ->orWhere($logDB.'.message_logs.pid', 'like', "%$searchValue%")
                   ->orWhere($logDB.'.message_logs.facility', 'like', "%$searchValue%")
                   ->orWhere($logDB.'.message_logs.msg', 'like', "%$searchValue%")
                   ->orWhere('r.name', 'like', "%$searchValue%")
                   ->orWhere('r.mac_address', 'like', "%$searchValue%")
                   ->orWhere('r.serial_number', 'like', "%$searchValue%")
                   ->orWhere('l.name', 'like', "%$searchValue%");
             });
         }
        if($user->role == 1)
        {
            $query->whereIn($logDB.'.message_logs.router_id',$routerArr);
        }        
        if(!empty($columncatval))
        {
            $query->where($logDB.'.message_logs.facility', 'like', '%' . $columncatval . '%');    
        }
        $locations = Location::pluck('name', 'id');
        $routers = Router::pluck('name', 'id');
        
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween($logDB.'.message_logs.created_at', [$formattedStartDate, $formattedEndDate]);
        }

        $sortableColumns = [
            'router_name' => 'r.name',
            'router_mac_address' => 'r.mac_address',
            'router_serial_number' => 'r.serial_number',
            'location_name' => 'l.name',
            'facility' => $logDB.'.message_logs.facility',
            'program' => $logDB.'.message_logs.program',
            'pid' => $logDB.'.message_logs.pid',
        ];
        $query->orderBy($sortableColumns[$columnName] ?? $logDB.'.message_logs.created_at', $columnSortOrder);      
        $totalRecordswithFilter = $query->count();  
        $logs = $query->
            take($rowperpage)
                ->skip($start)
                ->get();

        // $totalRecordswithFilter = $query->select($logDB.'.message_logs.*')->count();        
        // $totalRecordswithFilter = $totalRecords;
        // $logs = $query->orderBy($columnName, $columnSortOrder)
        //     ->skip($start)
        //     ->take($rowperpage)
        //     ->get();
        $data_arr = array();
        foreach ($logs as $log) {
            $id = $log->id;
            $msg = $log->msg;
            $pid = $log->pid;
            $program = $log->program;
            $facility = $log->facility;
            $createdAt = $log->created_at;
            $updatedAt = $log->updated_at;
            $routerName = $log->router_name;//$routers[$log->router_id] ?? 'NA';
            $routerSerialNo = $log->serial_number;//(isset($log->router))?$log->router->serial_number : "";
            $routermac_address = $log->mac_address;//(isset($log->router))?$log->router->mac_address : "";
            $routerLocationName = $log->location_name;//(isset($log->router->location))?$log->router->location->name : "";

            $data_arr[] = array(
                "id" => $id,
                "msg" => $msg,
                "program" => $program,
                "facility" => $facility,
                "pid" => $pid,
                "created_at" => $createdAt,
                "updated_at" => $updatedAt,
                "router_serial_number" =>$routerSerialNo,
                "location_name" => $routerLocationName,
                "router_mac_address" => $routermac_address,
                "router_name" =>$routerName
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
     * @param  \App\Models\UserIPAccessLog  $userIPAccessLog
     * @return \Illuminate\Http\Response
     */
    public function show(UserIPAccessLog $userIPAccessLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\UserIPAccessLog  $userIPAccessLog
     * @return \Illuminate\Http\Response
     */
    public function edit(UserIPAccessLog $userIPAccessLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\UserIPAccessLog  $userIPAccessLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, UserIPAccessLog $userIPAccessLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\UserIPAccessLog  $userIPAccessLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(UserIPAccessLog $userIPAccessLog)
    {
        //
    }
}
