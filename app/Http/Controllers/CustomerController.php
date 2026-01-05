<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function index(Request $request)
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
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        // Total records
        $totalRecords = '';
        $totalRecordswithFilter = '';
        $locations = Location::mine()->pluck('id')->toArray();

        $customersQuery = DB::table('users')->leftJoin('locations', 'users.location_id', '=', 'locations.id')
            ->select('users.*','users.id as id', 'users.first_name as first_name','users.email as email','users.phone as phone','users.email as email','users.created_at as created_at', 'users.location_id as user_location_id', 'users.last_login_at as last_login_at', 'locations.id as location_id', 'locations.name as name', 'locations.email as location_email', 'locations.phone as location_phone', 'locations.created_at as location_created_at')
            ->where(function ($query) use ($searchValue) {
                $query->where('users.first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.email', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.phone', 'like', '%' . $searchValue . '%');
            })
            ->where('users.role', 2)
            ->where('users.parent_id', null);
            
            $totalRecords = $customersQuery->count();
        if (!empty($startDate) && !empty($endDate)) {
            $startDateTimestamp = $startDate / 1000;
            $endDateTimestamp = $endDate / 1000;

            $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
            $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

            $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
            $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

            //for admin or distributor
            if ($user->isPDO()) {
                $pdoCustomersQuery =  DB::table('users')
                    ->where('role', 2)
                    ->join('locations', 'users.location_id', '=', 'locations.id')
                    ->select('users.*','users.id as id', 'users.first_name as first_name','users.email as email','users.phone as phone','users.email as email','users.created_at as created_at', 'users.location_id as user_location_id', 'users.last_login_at as last_login_at', 'users.suspend', 'locations.id as location_id', 'locations.name as name', 'locations.email as location_email', 'locations.phone as location_phone', 'locations.created_at as location_created_at')
                    ->where(function ($query) use ($searchValue) {
                        $query->where('users.first_name', 'like', '%' . $searchValue . '%')
                            ->orWhere('users.email', 'like', '%' . $searchValue . '%')
                            ->orWhere('users.phone', 'like', '%' . $searchValue . '%');
                    })
                    ->where('users.role', 2)
                    ->whereBetween('users.created_at', [$formattedStartDate, $formattedEndDate])
                    ->whereIn('users.location_id', $locations);

                $totalRecords = $pdoCustomersQuery->count();
                $totalRecordswithFilter = $pdoCustomersQuery->count();

                $customers = $pdoCustomersQuery->orderBy('users.created_at', 'desc')
                    ->take($rowperpage)
                    ->skip($start)
                    ->get();

            } else {
                $customersQuery = $customersQuery->whereBetween('users.created_at', [$formattedStartDate, $formattedEndDate]);

                $totalRecords = $customersQuery->count();
                $totalRecordswithFilter = $customersQuery->count();

                $customers = $customersQuery->orderBy('users.created_at', 'desc')
                    ->take($rowperpage)
                    ->skip($start)
                    ->get();                
            }
            
        } else {
            if($user->isPDO()) {
                $pdoCustomersQuery =  DB::table('users')
                    ->join('locations', 'users.location_id', '=', 'locations.id')
                    ->select('users.*','users.id as id', 'users.first_name as first_name','users.email as email','users.phone as phone','users.email as email','users.created_at as created_at', 'users.location_id as user_location_id', 'users.last_login_at as last_login_at', 'users.suspend','locations.id as location_id', 'locations.name as name', 'locations.email as location_email', 'locations.phone as location_phone', 'locations.created_at as location_created_at')
                    ->where(function ($query) use ($searchValue) {
                        $query->where('users.first_name', 'like', '%' . $searchValue . '%')
                            ->orWhere('users.email', 'like', '%' . $searchValue . '%')
                            ->orWhere('users.phone', 'like', '%' . $searchValue . '%');
                    })
                    ->where('users.role', 2)
                    ->whereIn('users.location_id', $locations);

                $totalRecords = $pdoCustomersQuery->count();
                $totalRecordswithFilter = $pdoCustomersQuery->count();

                $customers = $pdoCustomersQuery->orderBy('users.created_at', 'desc')
                    ->take($rowperpage)
                    ->skip($start)
                    ->get();
            } else {
                $totalRecords = $customersQuery->count();
                $totalRecordswithFilter = $customersQuery->count();

                $customers = $customersQuery->orderBy('users.created_at', 'desc')
                    ->take($rowperpage)
                    ->skip($start)
                    ->get();
            }
        }

        $data_arr = array();
        foreach ($customers as $customer) {
            $firstName = $customer->first_name ?? null;
            $email = $customer->email;
            $phone = $customer->phone;
            $created = $customer->created_at;
            $id = $customer->id;
            $name = $customer->name;
            $location_id = $customer->location_id;
            $userLocationId = $customer->user_location_id;
            $last_login_at = $customer->last_login_at;
            $suspend = $customer->suspend;

            $data_arr[] = array(
                'first_name' => $firstName,
                'email' => $email,
                'phone' => $phone,
                'created_at' => $created,
                'id' => $id,
                'name' => $name,
                'location_id' => $location_id,
                'user_location_id' => $userLocationId,
                'last_login_at' => $last_login_at,
                'suspend' => $suspend
        );
        }

        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        );

        return response()->json($response);

        /*$carbonStartDate = Carbon::createFromTimestamp($startDate);
        $formattedStartDate = $carbonStartDate->toDateString();
        $carbonendDate = Carbon::createFromTimestamp($endDate);
        $formattedendDate = $carbonendDate->toDateString();

        if(Auth::user()->isAdmin()) {
            $totalRecords =User::customers()->count();

            $totalRecordswithFilter = User::customers()->count();

            $customers = User::where('first_name', 'like', '%' . $searchValue . '%')
                ->orWhere('email', 'like', '%' . $searchValue . '%')
                ->orWhere('phone', 'like', '%' . $searchValue . '%')
                ->orWhereBetween('created_at', [$formattedStartDate, $formattedendDate])
                ->orderBy($columnName, $columnSortOrder)
                ->skip($start)
                ->take($rowperpage)
                ->get();
        }

        $data_arr = array();
        foreach ($customers as $customer) {

            $firstName = $customer->first_name ?? null;
            $email = $customer->email ;
            $phone = $customer->phone ;
            $created = $customer->created_at ;
            $id = $customer->id;

            $data_arr[] = array(
                'first_name' => $firstName,
                'email' => $email,
                'phone' => $phone,
                'created_at' => $created,
                'id' =>$id
            );
        }
        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        );

        return response()->json($response);*/


    }
}
