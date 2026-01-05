<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroups;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserGroupsController extends Controller
{
    public function index(Request $request)
    {
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
        $searchValue = $search_arr['value']; // For Search value

        $user = Auth::user();

        // Total records
        $totalRecords = UserGroups::where('pdo_id', $user->id)->where('type', 'group')->count();

        // Total records with filter
        $totalRecordswithFilter = UserGroups::where('pdo_id', $user->id)
            ->where('type', 'group')
            ->where(function ($query) use ($searchValue) {
                $query->where('user_id', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%');
            })
            ->count();

        // Fetch records with filter
        $records = UserGroups::where('pdo_id', $user->id)
            ->where('type', 'group')
            ->where(function ($query) use ($searchValue) {
                $query->where('user_id', 'like', '%' . $searchValue . '%')
                    ->orWhere('description', 'like', '%' . $searchValue . '%');
            })
            ->orderBy('created_at')
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $data_arr = array();
        foreach ($records as $record) {
            $data_arr[] = array(
                "id" => $record->id,
                "user_id" => $record->user_id,
                "description" => $record->description,
            );
        }

        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        return response()->json($response);
    }

    public function addCheckbox()
    {
        $user = Auth::user();

        $group = UserGroups::where('pdo_id', $user->id)->where('type', 'group')->get();

        return response()->json([
            'data' => $group,
            'message' => 'success',
        ], 200);
    }

    public function delete(Request $request)
    {
        $group = UserGroups::findorFail($request->id);
        $group->delete();

        DB::table('wifi_configuration_user_groups')->where('group_id', $request->id)->delete();

        return response()->json([
            'message' => 'group deleted successfully',
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        } else {
            $addGroup = UserGroups::create([
                'user_id' => $request->group_id,
                'description' => $request->group_description,
                'pdo_id' => Auth::user()->id,
                'type' => $request->type,
            ]);

            return response()->json([
                'message' => 'success',
                'data' => $addGroup,
            ], 200);
        }
    }

    public function edit(Request $request)
    {
        $group = UserGroups::where('id', $request->id)->first();
        return response()->json([
            'data' => $group,
            'message' => 'success',
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        } else {
            $group = UserGroups::findOrFail($request->id);
            $group->user_id = $request->group_id;
            $group->description = $request->group_description;
            $group->type = 'group';
            $group->save();

            return response()->json([
                'message' => 'group updated successfully',
            ], 200);
        }
    }

    public function storeUser(Request $request)
    {
        $userGroup = UserGroups::create([
            'user_id' => $request->title,
            'full_name' => $request->user_name ?? '',
            'password' => $request->user_password,
            'email' => $request->user_email ?? '',
            'type' => $request->type,
            'pdo_id' => Auth::user()->id,
        ]);

        $group_ids = explode(',', $request->group_ids);

        if ($group_ids) {
            foreach ($group_ids as $group_id) {

                $id = UserGroups::where('user_id', $group_id)->first();

                DB::table('wifi_configuration_user_groups')->insert([
                    'user_id' => $userGroup->id,
                    'group_id' => $id->id,
                ]);
            }
        }


        return response()->json([
            'message' => 'success',
        ], 200);
    }

    public function loadUsers(Request $request)
    {
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
        $searchValue = $search_arr['value']; // For Search value

        $user = Auth::user();

        // Fetch the total number of records
        $totalRecords = UserGroups::where('pdo_id', $user->id)->where('type', 'users')->count();

        // Fetch the total number of records with filter
        $totalRecordswithFilter = UserGroups::where('pdo_id', $user->id)
            ->where('type', 'users')
            ->where(function ($query) use ($searchValue) {
                $query->where('full_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('email', 'like', '%' . $searchValue . '%')
                    ->orWhere('user_id', 'like', '%' . $searchValue . '%');
            })
            ->count();

        $userGroups = UserGroups::where('pdo_id', $user->id)
            ->where('type', 'users')
            ->where(function ($query) use ($searchValue) {
                $query->where('full_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('email', 'like', '%' . $searchValue . '%')
                    ->orWhere('user_id', 'like', '%' . $searchValue . '%');
            })
            ->orderBy('created_at')
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $finalData = [];

        foreach ($userGroups as $userGroup) {

            $groups = DB::table('wifi_configuration_user_groups')
                ->where('user_id', $userGroup->id)
                ->pluck('group_id')
                ->toArray();

            $userGroupArray = $userGroup->toArray();
            $userGroupArray['group_ids'] = $groups;
            $finalData[] = $userGroupArray;
        }

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecordswithFilter,
            'data' => $finalData,
            'message' => 'success',
        ]);
    }

    public function deleteUser(Request $request)
    {

        // Find the user group by ID
        $userGroup = UserGroups::findOrFail($request->id);
        $userGroup->delete();


        $ids = DB::table('wifi_configuration_user_groups')
            ->where('user_id', $request->id)
            ->pluck('group_id')
            ->toArray();

        if ($ids) {
            DB::table('wifi_configuration_user_groups')
                ->where('user_id', $request->id)
                ->delete();
        }

        // Delete the user group

        return response()->json([
            'message' => 'User deleted successfully',
        ], 200);
    }


    public function editUser(Request $request)
    {
        $editUserGroup = UserGroups::where('id', $request->id)->first();

        $groups = DB::table('wifi_configuration_user_groups')
            ->where('user_id', $editUserGroup->id)
            ->pluck('group_id')
            ->toArray();

        $editUserGroupArray = $editUserGroup->toArray();

        $finalData = array_merge($editUserGroupArray, ['groups' => $groups]);

        return response()->json([
            'data' => $finalData,
            'message' => 'success',
        ], 200);
    }

    public function updateUser(Request $request)
    {
        $updateUserGroup = UserGroups::where('id', $request->id)->first();
        $updateUserGroup->user_id = $request->title;
        $updateUserGroup->full_name = $request->user_name ?? '';
        $updateUserGroup->password = $request->user_password;
        $updateUserGroup->email = $request->user_email ?? '';
        $updateUserGroup->save();

        $group_ids = explode(',', $request->group_ids);

        DB::table('wifi_configuration_user_groups')
            ->where('user_id', $updateUserGroup->id)
            ->delete();

        foreach ($group_ids as $groupId) {
            $id = UserGroups::where('user_id', $groupId)->first();

            DB::table('wifi_configuration_user_groups')->insert([
                'user_id' => $updateUserGroup->id,
                'group_id' => $id->id,
            ]);
        }

        return response()->json([
            'message' => 'User groups updated successfully'
        ], 200);
    }

    public function storeGuest(Request $request)
    {
        $user = Auth::user();

        if ($request->guest_number) {
            $prefixArray = [];
            $passwordArray = [];
            for ($i = 0; $i < $request->guest_number; $i++) {
                $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $randomString = substr(str_shuffle($characters), 0, 6);
                $password = substr(str_shuffle($characters), 0, 9);
                $prefix = $request->guest_prefix;

                $finalPrefix = $prefix . '_' . $randomString;
                $prefixArray[] = $finalPrefix;
                $passwordArray[] = $password;
            }
        }

        if (isset($prefixArray) && !empty($prefixArray)) {
            if ($request->expiry_date) {
                $expiryDate = $this->calculateTime($request->expiry_date);
            }

            foreach ($prefixArray as $array) {
                $guestGroup = UserGroups::create([
                    'user_id' => $array,
                    'description' => $request->guest_description ?? '',
                    'type' => $request->type ?? '',
                    'expiry_date' => $expiryDate ?? '',
                    'email' => $request->user_email ?? '',
                    'pdo_id' => $user->id,
                ]);
            }
        } else {
            if ($request->expiry_date) {
                $expiryDate = $this->calculateTime($request->expiry_date);
            }
            $guestGroup = UserGroups::create([
                'description' => $request->guest_description ?? '',
                'type' => $request->type ?? '',
                'full_name' => $request->guest_name ?? '',
                'password' => $request->guest_password ?? '',
                'email' => $request->user_email ?? '',
                'user_id' => $request->title ?? '',
                'pdo_id' => $user->id,
                'expiry_date' => $expiryDate ?? '',
            ]);
        }

        return response()->json([
            'message' => 'success',
        ], 200);
    }

    function calculateTime($time)
    {
        $new_time = '';
        $format = str_split($time, 1);
        $now = time(); // Get the current Unix timestamp
        if ($format[1] == 'h' || $format[2] == 'h') {
            $new_time = date("Y-m-d H:i:s", strtotime("+$format[0] hours", $now));
        } elseif ($format[1] == 'd') {
            $new_time = date('Y-m-d H:i:s', strtotime('+' . $format[0] . ' day', $now));
        }
        return $new_time;
    }

    public function loadGuests(Request $request)
    {
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
        $searchValue = $search_arr['value']; // For Search value

        $user = Auth::user();

        // Total records
        $totalRecords = UserGroups::where('pdo_id', $user->id)->where('type', 'guests')->count();

        // Total records with filter
        $totalRecordswithFilter = UserGroups::where('pdo_id', $user->id)
            ->where('type', 'guests')
            ->where(function ($query) use ($searchValue) {
                $query->where('full_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('email', 'like', '%' . $searchValue . '%')
                    ->orWhere('user_id', 'like', '%' . $searchValue . '%');
            })
            ->count();

        // Fetch records with filter
        $records = UserGroups::where('pdo_id', $user->id)
            ->where('type', 'guests')
            ->where(function ($query) use ($searchValue) {
                $query->where('full_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('email', 'like', '%' . $searchValue . '%')
                    ->orWhere('user_id', 'like', '%' . $searchValue . '%');
            })
            ->orderBy($columnName, $columnSortOrder)
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $data_arr = array();
        foreach ($records as $record) {
            $data_arr[] = array(
                'id' => $record->id,
                "user_id" => $record->user_id,
                "full_name" => $record->full_name,
                "email" => $record->email,
                "description" => $record->description,
                "expiry_date" => $record->expiry_date,
            );
        }

        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        return response()->json($response);
    }

    public function deleteGuests(Request $request)
    {
        $deleteGuest = UserGroups::findOrFail($request->id);
        $deleteGuest->delete();

        return response()->json([
            'message' => 'deleted successfilly',
        ], 200);
    }

    public function editGuests(Request $request)
    {
        $editGuest = UserGroups::where('id', $request->id)->first();

        return response()->json([
            'data' => $editGuest,
            'message' => 'success',
        ], 200);
    }

    public function updateGuests(Request $request)
    {
        $updateGuest = UserGroups::where('id', $request->id)->first();
        if ($request->expiry_date) {
            $expiryDate = $this->calculateTime($request->expiry_date);
        }
        $updateGuest->user_id = $request->title ?? '';
        $updateGuest->full_name = $request->guest_name ?? '';
        $updateGuest->description = $request->guest_description ?? '';
        $updateGuest->expiry_date = $expiryDate ?? '';
        $updateGuest->email = $request->user_email ?? '';
        $updateGuest->save();

        return response()->json([
            'message' => 'updated successfilly',
        ], 200);
    }
}
