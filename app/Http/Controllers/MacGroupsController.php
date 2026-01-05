<?php

namespace App\Http\Controllers;

use App\Models\MacGroups;
use App\Models\User;
use App\Models\UserGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MacGroupsController extends Controller
{

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'mac_address' => 'required|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        } else {

            $macGroup = MacGroups::create([
                'title' => $request->title,
                'mac_address' => json_encode($request->mac_address),
                'pdo_id' => Auth::user()->id,
            ]);

            return response()->json([
                'message' => 'Success',
            ], 200);
        }
    }

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

        $totalRecords = MacGroups::where('pdo_id', $user->id)->count();

        $totalRecordswithFilter = MacGroups::where('pdo_id', $user->id)
            ->where(function ($query) use ($searchValue) {
                $query->where('title', 'like', '%' . $searchValue . '%')
                    ->orWhere('mac_address', 'like', '%' . $searchValue . '%');
            })
            ->count();

        $records = MacGroups::where('pdo_id', $user->id)
            ->where(function ($query) use ($searchValue) {
                $query->where('title', 'like', '%' . $searchValue . '%')
                    ->orWhere('mac_address', 'like', '%' . $searchValue . '%');
            })
            ->orderBy('created_at')
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $data_arr = array();
        foreach ($records as $record) {
            $data_arr[] = array(
                "id" => $record->id,
                "title" => $record->title,
                "mac_address" => $record->mac_address,
                "is_disable" => $record->is_disable,
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


    public function edit(Request $request)
    {
        $macGroups = MacGroups::where('id', $request->id)->get();

        return response()->json([
            'data' => $macGroups,
            'message' => 'success'
        ], 200);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'mac_address' => 'required|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        } else {
            $macGroups = MacGroups::where('id', $request->id)->first();
            $macGroups->title = $request->title;
            $macGroups->mac_address = $request->mac_address;
            $macGroups->save();

            return response()->json([
                'data' => $macGroups,
                'message' => 'updated successfully'
            ], 200);
        }
    }

    public function delete(Request $request)
    {
        $macGroup = MacGroups::findorFail($request->id);
        $macGroup->delete();

        return response()->json([
            'message' => 'deleted successfully'
        ], 200);
    }

    public function disable(Request $request)
    {
        $macGroup = MacGroups::findorFail($request->id);
        $macGroup->is_disable = 1;
        $macGroup->save();

        return response()->json([
            'message' => 'disabled successfully'
        ], 200);
    }

    public function enable(Request $request)
    {
        $macGroup = MacGroups::findorFail($request->id);
        $macGroup->is_disable = 0;
        $macGroup->save();

        return response()->json([
            'message' => 'disabled successfully'
        ], 200);
    }
}
