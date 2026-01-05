<?php

namespace App\Http\Controllers;

use App\Models\BrandingProfile;
use App\Models\Location;
use App\Models\NetworkSettings;
use App\Models\PdoNetworkSetting;
use App\Models\Router;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Validator;
use App\Models\WifiConfigurationProfiles;


class NetworkSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $networkSettings = NetworkSettings::where('id', 1)->get();
        if ($networkSettings) {
            return response()->json([
                'status' => true,
                'message' => 'Network Settings retrived',
                'networkSettings' => $networkSettings
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Network Settings not exists',
            ], 401);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'essid' => 'required|string|between:1,32',
            'radiusServer' => 'required|string',
            'radiusSecret' => 'required|string',
            'loginUrl' => 'required|string',
            'log_server_url' => 'required|string',
            'free_download' => 'required|integer',
            'support_essid' => 'nullable|max:255',
            'support_essid_password' => 'nullable|max:255',
            'support_essid_hide' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $validatedData = $validator->validated();

        $timezoneData = json_encode([
            'timezone' => $request->timezone,
            'zonename' => $request->zonename
        ]);

        $validatedData['timezone'] = $timezoneData;

        $networkSettings = NetworkSettings::create($validatedData);

        return response()->json([
            'message' => 'Network successfully created',
            'networkSettings' => $networkSettings
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\NetworkSettings $networkSettings
     * @return \Illuminate\Http\Response
     */
    public function show(NetworkSettings $networkSettings)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\NetworkSettings $networkSettings
     * @return \Illuminate\Http\Response
     */
    public function edit(NetworkSettings $networkSettings)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\NetworkSettings $networkSettings
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $requestData = $request->all();
        $validator = Validator::make($requestData, [
            'id' => 'required|integer||exists:network_settings,id|',
            'essid' => 'required|string|between:1,32',
            'radiusServer' => 'required|string',
            'radiusSecret' => 'required|string',
            'loginUrl' => 'required|string',
            'log_server_url' => 'required|string',
            'free_download' => 'required|integer',
            'support_essid' => 'nullable|max:255',
            'support_essid_password' => 'nullable|max:255',
            'support_essid_hide' => 'nullable|integer',
            'user_dns' => 'nullable', // Ensure it's an array
            'device_dns' => 'nullable', // Ensure it's an array
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $networkSettings = NetworkSettings::findOrFail($validator->validated()['id']);

        $input = $request->all();

        $userDns = $request->input('user_dns');
        $deviceDns = $request->input('device_dns');

        // Convert arrays to JSON strings
        $userDnsJson = json_encode($userDns);
        $deviceDnsJson = json_encode($deviceDns);
        $timezoneData = json_encode([
            'timezone' => $request->timezone,
            'zonename' => $request->zonename
        ]);

        // Fill model attributes
        $networkSettings->fill(array_merge($validator->validated(), [
            'user_dns' => $userDnsJson,
            'device_dns' => $deviceDnsJson,
            'timezone' => $timezoneData
        ]));


        //$networkSettings->fill($validator->validated());


       if ($networkSettings->isDirty()) {
          Router::increment('configurationVersion');
       }


        $networkSettings->save();

        return response()->json([
            'status' => true,
            'message' => 'Network Setting updated',
            'networkSettings' => $networkSettings
        ], 201);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\NetworkSettings $networkSettings
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(NetworkSettings $networkSettings)
    {
        //
    }
    private function convertKeys($array)
    {
        $convertedArray = [];
        foreach ($array as $key => $value) {
            $newKey = str_replace('-', '_', $key);
            if (is_array($value)) {
                $value = $this->convertKeys($value);
            }
            $convertedArray[$newKey] = $value;
        }
        return $convertedArray;
    }

    public function ssidControl (Request $request)
    {
        $jsonData = array();
        foreach ($request->all() as $key => $value) {
            if (preg_match('/^(.*?)-(\d+)$/', $key, $matches)) {
                $field = $matches[1];
                $suffix = $matches[2];

                if (!in_array($field, ['channel', 'channel-5g', 'ntp-server-1', 'ntp-server-2', 'ntp-server'])) {
                    if (!isset($jsonData[$suffix - 1])) {
                        $jsonData[$suffix - 1] = [];
                    }

                    $jsonData[$suffix - 1][$field] = $value === "on" ? true : $value;
                    $jsonData[$suffix - 1]['mode'] = 'ap';
                }
            }
        }
        $jsonData = array_values($jsonData);
        $jsonData = $this->convertKeys($jsonData);

        $user = Auth::user();
        if (!$user) {
            // If user does not exist, return a JSON response
            return response()->json([
                'status' => false,
                'message' => 'User Does Not Exist!',
            ], 200);
        }

         // Assuming 'id' is the primary key of 'PdoNetworkSetting' table
        $pdoNetworkSettings = PdoNetworkSetting::where('pdo_id', $user->id)->first();

        $location = Location::where('owner_id',$user->id)->first();
        $router = Router::where('location_id',$location->id)->where('owner_id',$location->owner_id)->get();

        if (!$pdoNetworkSettings) {
            // If 'PdoNetworkSetting' record does not exist for the user, create a new one
            $pdoNetworkSettings = new PdoNetworkSetting();
            $pdoNetworkSettings->pdo_id = $user->id;
            $pdoNetworkSettings->essid = $request->essid;
            $pdoNetworkSettings->subnet_mask = $request->subnet_mask;
            $pdoNetworkSettings->lease_time = $request->lease_time;
            $pdoNetworkSettings->advance_setting = json_encode($jsonData[0]);
            $pdoNetworkSettings->save();

            foreach ($router as $router) {
                $router->increment('configurationVersion');
            }

        } else {
            // If 'PdoNetworkSetting' record exists, update it
            $pdoNetworkSettings->essid = $request->essid;
            $pdoNetworkSettings->subnet_mask = $request->subnet_mask;
            $pdoNetworkSettings->lease_time = $request->lease_time;
            $pdoNetworkSettings->advance_setting = json_encode($jsonData[0]);
            $pdoNetworkSettings->save();

            foreach ($router as $router) {
                $router->increment('configurationVersion');
            }
        }

       // Return a JSON response indicating success or any additional data needed
        return response()->json([
            'status' => true,
            'message' => 'PdoNetworkSetting updated successfully!',
            // You can add any additional data needed here
        ],200);

    }
    public function loadZone (Request $request)
    {
        $pdo_location = null;
        $pdo_zone = null;
        if (Auth::user()->isPDO()) {
            $locationsnew = Location::where('owner_id', Auth::user()->id)->get();

            if ($locationsnew->isNotEmpty()) {
                foreach ($locationsnew as $locationpdo) {
                    $location_id = $locationpdo->id;
                    $location_name = $locationpdo->name;

                    $pdo_location = array(
                        'id' => $location_id,
                        'name' => $location_name,
                    );
                }
            } else {
                $pdo_location = null;
            }

            $zones = BrandingProfile::where('pdo_id', Auth::user()->id)->get();

            if ($zones->isNotEmpty()) {
                foreach ($zones as $zone) {
                    $zone_id = $zone->id;
                    $zone_name = $zone->name;

                    $pdo_zone = array(
                        'id' => $zone_id,
                        'name' => $zone_name,
                    );
                }
            } else {
                $pdo_zone = null;
            }
        }
        return compact('pdo_location', 'pdo_zone');

    }

    public  function loadSsidControl ()
    {

        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User Does Not Exist!',
            ], 200);
        }

        $pdoNetworkSettings = PdoNetworkSetting::where('pdo_id', $user->id)->first();

        if (!$pdoNetworkSettings) {
            return response()->json([
                'status' => false,
                'message' => 'PdoNetworkSetting not found for this user!',
            ], 404);
        }

        // Return pdo_id and essid in the response
        return response()->json([
            'status' => true,
            'pdo_id' => $pdoNetworkSettings->pdo_id,
            'essid' => $pdoNetworkSettings->essid,
            'subnet_mask' => $pdoNetworkSettings->subnet_mask,
            'lease_time' => $pdoNetworkSettings->lease_time,
            'advance_setting' => $pdoNetworkSettings->advance_setting,
        ]);
    }

}

