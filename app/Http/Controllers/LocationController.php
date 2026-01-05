<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateLocationForm;
use App\Models\Location;
use App\Models\Router;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Validator;
use Symfony\Component\HttpFoundation\Response;

class LocationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['list', 'show']]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $query = Location::with('routers', 'owner', 'profile');

        $query->mine();

        if ($user->isPDO()) {
            $query = $query->where('owner_id', $user->id)->get();
            return $query;
        }

        return $query->get();
    }

    public function getLocation(Request $request)
    {
        return Location::with('routers', 'owner')->where('owner_id', $request->pdoId)->mine()->get();
    }

    public function store(CreateLocationForm $form)
    {
        return $form->save();
    }

    public function show(Location $location)
    {
        $location->load('owner', 'routers','wifiConfigurationProfileLocation');
        if(Auth::user()->isPDO()){
            if($location->owner_id == Auth::user()->id) {
                $location->load('owner', 'routers');
                return $location;
            } else {
                return response()->json(['status'=> false , "message"=> "Unathenticated"],200);
            }
        }
        return $location;
    }


    function update_private_network(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'personal_essid' => 'required|string|between:1,32',
            'personal_essid_password' => 'required|string|between:8,32',
            'id' => 'required|integer'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $location = Location::find($request->id);
        if (($location->personal_essid != $request->personal_essid) || ($location->personal_essid_password != $request->personal_essid_password)) {
            $location->personal_essid = $request->personal_essid;
            $location->personal_essid_password = $request->personal_essid_password;
            $location->save();
            //return "aa";
            $wifiRouter = Router::where('location_id', $request->id)->first();
            if ($wifiRouter) {
                $wifiRouter->last_configuration_version = $wifiRouter->configurationVersion;
                $wifiRouter->last_updated_at = $wifiRouter->updated_at;
                $wifiRouter->increment('configurationVersion');
                $wifiRouter->save();
            }
            //$input = $request->all();
        }

        return $location;
    }

    public function info($location_id)
    {
        return Location::where('id', $location_id)->get();
    }

    public function generateLicenseKey()
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $finalKey = substr(str_shuffle($characters),0,24);

        return response()->json([
            'key' => $finalKey,
            'message' => 'success',
        ],200);
    }

    function destroy($id){
        try{
            $deviceLocation = Location::findOrFail($id);

            // Check if inventory is assigned to this model_id
            $inventoryExists = Router::where('location_id', $deviceLocation->id)->exists();
            
            if ($inventoryExists) {
                return response()->json([
                    'message' => 'Inventory has been assigned to this Location, cannot delete.'
                ], Response::HTTP_NON_AUTHORITATIVE_INFORMATION); //203
            }

            $deviceLocation->delete();

            return response()->json([
                'message' => 'Location deleted successfully.',
            ],200);
        }
        catch(\Exception $e){
            Log::error('Issue on Deleting Model', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something went wrong deleting location', 'error' => $e->getMessage()], 500);
        }

    }

}
