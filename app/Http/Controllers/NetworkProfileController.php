<?php

namespace App\Http\Controllers;

use App\Models\NetworkProfile;
use App\Models\NetworkSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\CentralUserService;

class NetworkProfileController extends Controller
{
    public $datetimeFormat = 'd-m-Y H:i';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // $user = Auth::user();
        // if ($user->parent_id) {
        //     $user = User::find($user->parent_id);
        // }

        $user = CentralUserService::resolve($request);
        //dd($user);
        // if ($request->ajax()) {


            $draw = $request->get('draw');
            $start = $request->get("start");
            $rowperpage = $request->get("length"); // rows per page

            $columnIndex_arr = $request->get('order');
            $columnName_arr = $request->get('columns');
            $order_arr = $request->get('order');
            $search_arr = $request->get('search');

            $columnIndex = $columnIndex_arr[0]['column']; // column index
            $columnName = $columnName_arr[$columnIndex]['data']; // column name
            $columnSortOrder = $order_arr[0]['dir']; // asc or desc
            $searchValue = !empty($search_arr['value']) ? $search_arr['value'] : ''; // search value

            // Base query
            $query = NetworkProfile::where('is_deleted', 0)
                        ->where('pdo_id', $user->id); // or remove this if not needed:


            // Total records
            $totalRecords = $query->count();

            if($request->get("type") != "all"){
                $query->where("type", $request->get("type"));
            }

            // Apply search filter
            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue) {
                    $q->where('name', 'like', "%{$searchValue}%")
                      ->orWhere('type', 'like', "%{$searchValue}%");
                });
            }

            // Total records with filter
            $totalRecordswithFilter = $query->count();

            // Ordering
            $query->orderBy($columnName, $columnSortOrder);

            // Pagination
            $records = $query->skip($start)->take($rowperpage)->get();
            $data = [];

            foreach ($records as $index => $record) {
                $prefix = $record->type;
                $data[] = [
                    "id"          => $record->id,
                    "name"        => $record->name,
                    "type"        => $record->type,
                    "created_at"  => Carbon::parse($record->created_at)->format($this->datetimeFormat),
                    "content"     => json_encode($record->content, JSON_PRETTY_PRINT), 
                    "profile_status" => $record->profile_status, 
                    "actions" => [
                        "edit_route" => 'api/' . $prefix . '-profile.display', //route($prefix . '-profile.display'),
                        "id" => $record->id,
                    ],
                ];
            }

            // Response for DataTables
            $response = [
                "draw" => intval($draw),
                "iTotalRecords" => $totalRecords,
                "iTotalDisplayRecords" => $totalRecordswithFilter,
                "aaData" => $data
            ];

            return response()->json($response);
        // }

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
        // $user = Auth::user();
        // if ($user->parent_id) {
        //     $user = User::find($user->parent_id);
        // }

        $user = CentralUserService::resolve($request);

        
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:snmp,ntp,qos,network,domainfilter,syslog,macwhitelist',
            'name' => 'required|string|max:255',
            // 'content' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // dd($request);
           if ($request->filled('prof_id')) {
            $profile = NetworkProfile::findOrFail($request->prof_id);
            $profile->update([
                'type' => $request->type,
                'name' => $request->name,
                'content' => $request->content,
                'status' => $request->status,
                'pdo_id'  => $user->id
            ]);

            $status = 200; // Updated

            }else{
                $profile = NetworkProfile::create([
                    'type' => $request->type,
                    'name' => $request->name,
                    'content' => $request->content,
                    'status' => $request->status,
                    'pdo_id'  => $user->id
                ]);
            $status = 200; // Updated

            }

            DB::commit();
            return response()->json($profile, $status);

        } catch (\Exception $e) {
            DB::rollBack();
            // Return a more detailed error message for debugging
            return response()->json([
                'error' => 'Failed to save profile due to a database error.',
                'message' => $e->getMessage()
            ], 500);

        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $profile = NetworkProfile::findOrFail($id);
        return response()->json($profile);
    }

    public function display($id){

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $profile = NetworkProfile::findOrFail($id);
        // dd($profile->type);
        switch ($profile->type) {
            case 'snmp':
                // return view('snmp-profile-edit', compact('profile'));
                return view('snmp-profile-edit');
            // Add cases for other profile types like ntp, qos if they have different edit views
            default:
                // Generic edit view or redirect with an error
                return redirect()->route('network-profiles.index')->with('error', 'Unknown profile type.');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:snmp,ntp,qos',
            'name' => 'required|string|max:255',
            'content' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $profile = NetworkProfile::findOrFail($id);
            $profile->update([
                'type' => $request->type,
                'name' => $request->name,
                'content' => $request->content,
            ]);

            DB::commit();
            return response()->json($profile, 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update profile due to a database error.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $profile = NetworkProfile::findOrFail($id);
            $profile->is_deleted = 1; // mark as deleted
            $profile->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile deleted successfully (soft delete).'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProfileTabs()
    {
        try {
            $profiles = NetworkProfile::select('type')
                ->where('is_deleted', 0)
                ->where('pdo_id', Auth::user()->id) // or remove this if not needed
                ->groupBy('type')
                ->pluck('type');
            // dd($profiles->toSql());
            return response()->json([
                'data' => $profiles,
                'message' => 'success'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // public function getProfileDropdowns()
    // {
    //     try {
    //         $profiles = NetworkProfile::select('id', 'name', 'type')
    //             ->where('is_deleted', 0)
    //             ->where('profile_status', 'active')
    //             ->where("type", request()->get("type"))
    //             ->where('pdo_id', Auth::user()->id) // or remove this if not needed
    //             ->get();

    //         return response()->json([
    //             'data' => $profiles,
    //             'message' => 'success'
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // public function toggleStatus($profileId)
    // {
    //     try {
    //         $profile = NetworkProfile::findOrFail($profileId);
    //         $profile->profile_status = ($profile->profile_status === 'active') ? 'inactive' : 'active';
    //         $profile->save();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Profile status updated successfully.',
    //             'new_status' => $profile->profile_status
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to update profile status.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}
