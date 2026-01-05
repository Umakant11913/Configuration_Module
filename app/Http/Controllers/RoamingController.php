<?php

namespace App\Http\Controllers;


use App\Models\Location;
use App\Models\WifiConfigurationProfiles;
use Carbon\Carbon;
use http\Env\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class RoamingController extends Controller
{
    /**
     * @var null
     */

    public function index(Request $request)
    {
        if (!$request->has('mac') || !$request->has('nasid')) {
            return response()->json([
                'status' => 'false',
                'message' => 'MAC address & NAS ID are required',
            ], 400);
        }

        $macAddress = $request->mac;
        $nasId = $request->nasid;

        $location = Location::where('id', $nasId)->first();
        $configProfile = WifiConfigurationProfiles::where('id', $location->wifi_configuration_profile_id)->first();

        $roamingValue = "";

        if($configProfile) {
            $settings = json_decode($configProfile->settings, true);
            $roamingValue = $settings['roaming'] ?? "off";
        }

        $radius_wifiuser = DB::connection('mysql2')->table('radacct')
            ->where('callingstationid', $macAddress)->where('location_id', $nasId)->where('acctstoptime', null)
            ->orderBy('acctupdatetime')->first();

        if($radius_wifiuser && $roamingValue !== "off") {
            $username = $radius_wifiuser->username;

            $radcheck = DB::connection('mysql2')->table('radcheck')
                ->where('username', $username)->orderBy('id', 'desc')->first();

            $password = $radcheck->value;
            $phone_number = $radcheck->phone_number;

//            DB::connection('mysql2')->table('radacct')
//                ->where('radacctid', $radius_wifiuser->radacctid)
//                ->update([
//                    'acctstoptime' => now(),
//                    'acctterminatecause' => 'Disconnected',
//                ]);

            return response()->json([
                'status' => 'true',
                'username' => $username,
                'password' => $password,
                'phone_number' => !empty($phone_number) ? $phone_number : null
            ], 201);

        } else {
            return response()->json([
                'status' => 'false',
                'username' => NULL,
                'password' => NULL,
                'phone_number' => NULL
            ], 404);
        }

    }

}
