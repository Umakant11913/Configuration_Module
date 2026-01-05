<?php

namespace App\Http\Controllers;

use App\Http\Controllers\WiFRouterController;
use App\Models\Location;
use App\Models\MacGroups;
use App\Models\Router;
use App\Models\User;
use App\Models\UserGroups;
use App\Models\PdoNetworkSetting;
use App\Models\NetworkSettings;
use App\Models\WifiConfigurationProfiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class WifiConfigurationProfilesController extends Controller
{
    private function toBoolean($value)
    {
        return $value === "on" ? true : $value;
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
        $searchValue = $search_arr['value']; // For Search value

        $totalRecords = '';
        $totalRecordswithFilter = '';

        $query = WifiConfigurationProfiles::with('locations', 'pdo')
            ->leftJoin('users', 'wifi_configuration_profiles.pdo_id', '=', 'users.id')
            ->select('wifi_configuration_profiles.*', 'users.id as pdo_id', 'users.first_name as first_name', 'users.last_name as last_name', 'users.created_at as user_created_at')
            ->where(function ($query) use ($searchValue) {
                $query->where('users.first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('users.last_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('wifi_configuration_profiles.title', 'like', '%' . $searchValue . '%');
            })
            ->where('wifi_configuration_profiles.pdo_id', Auth::user()->id);

        $totalRecords = $query->count();
        $totalRecordswithFilter = $query->count();

        $settings = $query->orderBy('wifi_configuration_profiles.created_at', 'desc')
            ->take($rowperpage)
            ->skip($start)
            ->get();

        $data_arr = array();
        foreach ($settings as $setting) {
            $id = $setting->id;
            $title = $setting->title ?? null;
            $first_name = $setting->first_name ?? null;
            $last_name = $setting->last_name;
            $disabled = $setting->disabled;
            $published = $setting->published;
            $created_at = $setting->created_at;
            $locations = Location::where('wifi_configuration_profile_id', $setting->id)->pluck('name', 'id');
            $routers = Router::where('wifi_configuration_profile_id', $setting->id)->pluck('name', 'id');

            $data_arr[] = array(
                'id' => $id,
                'title' => $title,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'disabled' => $disabled,
                'published' => $published,
                'created_at' => $created_at,
                'location' => $locations,
                'router' => $routers
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

    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $title = $request->input('title');
        $ntpServer1 = $request->input('ntp-server-1');
        $ntpServer2 = $request->input('ntp-server-2');
        $roaming = $request->input('roaming') != NULL ? 'on' : 'off';

        $snmpName = $request->input('snmp_name');
        $engineId = $request->input('engine_id');
        $contact = $request->input('contact');
        $comment = $request->input('comment');
        $cpuThreshold = $request->input('cpu_threshold');
        $memThreshold = $request->input('mem_threshold');
        $communityJson = $request->input('community_array');
        $communityArray = json_decode(json_encode($communityJson), true);
        $userJson = $request->input('user_array');
        $userArray = json_decode(json_encode($userJson), true);

        $radio2g = $request->input('radio-2g');
        $radio5g = $request->input('radio-5g');
        $zoneName = $request->input('zone_name');
        $timeZone = $request->input('time_zone');

        $uploadLimit = $request->input('upload_limit');
        $downloadLimit = $request->input('download_limit');
        $timezoneArray = [
            'zonename' => $zoneName ?? '',
            'timezone' => $timeZone ?? ''
        ];

        $timezoneFormatted = json_encode($timezoneArray);
        $jsonData = [
            'ntp_server_1' => $ntpServer1,
            'ntp_server_2' => $ntpServer2,
            'roaming' => $roaming,
            'time_zone' => $timezoneFormatted,
            'snmp' => [
                'snmp_name' => $snmpName,
                'engine_id' => $engineId,
                'contact' => $contact,
                'comment' => $comment,
                'cpu_threshold' => $cpuThreshold,
                'mem_threshold' => $memThreshold,
                'community' => $communityArray,
                'users' => $userArray,
            ],
            'radio_2g' => [
                'enabled' => $this->toBoolean($radio2g),
                'ht_mode' => $request->input('ht-mode'),
                'bandwidth' => $request->input('bandwidth'),
                'tx_power' => $request->input('tx-power'),
                'channel' => []
            ],
            'radio_5g' => [
                'enabled' => $this->toBoolean($radio5g),
                'ht_mode_5g' => $request->input('ht-mode-5g'),
                'bandwidth_5g' => $request->input('bandwidth-5g'),
                'tx_power_5g' => $request->input('tx-power-5g'),
                'channel' => [],
                'mu_mimo' => $request->input('mu-mimo') === 'on'
            ],
            'ssid' => [],
            'domain_filter' => [],
            'upload_limit' => $uploadLimit,
            'download_limit' => $downloadLimit,
        ];

        $channels2g = [];
        $channels5g = [];
        $domainFilter=[];


        foreach ($request->all() as $key => $value) {

             /** Skip PM-WANI fields completely */
            if (str_starts_with($key, 'pm-wani') || str_starts_with($key, 'pm_wani')) {
                continue;
            }
	    if ($value === null || $value === '') {
            	continue;
            }

            // if (preg_match('/^channel(?:-5g)?-(\d+)$/', $key, $matches)) {
            //     $channelNumber = (int)$matches[1];
            //     if (strpos($key, 'channel-5g') === 0) {
            //         $channels5g[] = $channelNumber;
            //     } else {
            //         $channels2g[] = $channelNumber;
            //     }
            // } 
            if (preg_match('/^channel(?:-5g)?$/', $key, $matches)) {
                $key = trim(strtolower($key));
                if ($key === 'channel-5g') {
                    $channels5g[] = $value;
                } elseif ($key === 'channel') {
                    $channels2g[] = $value;
                }
            }
            elseif (preg_match('/^(whitelist|blacklist)_ip_(\d+)$/', $key, $matches)) {
                $type = $matches[1];
                $suffix = (int)$matches[2];

                if (!in_array($suffix, ['channel', 'channel-5g', 'ntp-server-1', 'ntp-server-2', 'ntp-server'])) {
                    if (!isset($domainFilter[$suffix])) {
                        $domainFilter[$suffix] = [];
                    }

                    $domainFilter[$suffix][$type . '_ip'] = $value;
                }
            } elseif (preg_match('/^(.*?)-(\d+)$/', $key, $matches)) {
                $field = $matches[1];
                $suffix = $matches[2];

                if (!in_array($field, ['channel', 'channel-5g', 'ntp-server-1', 'ntp-server-2', 'ntp-server'])) {
                    if (!isset($jsonData['ssid'][$suffix - 1])) {
                        $jsonData['ssid'][$suffix - 1] = [];
                    }

                    $jsonData['ssid'][$suffix - 1][$field] = $this->toBoolean($value);
                    $jsonData['ssid'][$suffix - 1]['mode'] = 'ap';
                }
            }
        }

        $whitelistIp = [];
        $blacklistIp = [];

        foreach ($domainFilter as $item) {
            if (isset($item['whitelist_ip']) && !empty($item['whitelist_ip'])) {
                $whitelistIp[] = $item['whitelist_ip'];
            }
            if (isset($item['blacklist_ip']) && !empty($item['blacklist_ip'])) {
                $blacklistIp[] = $item['blacklist_ip'];
            }
        }

        $domainFilter = [
            'whitelist_ip' => $whitelistIp,
            'blacklist_ip' => $blacklistIp
        ];

        $jsonData['domain_filter'] = $domainFilter;

        $jsonData['radio_2g']['channel'] = $channels2g;
        $jsonData['radio_5g']['channel'] = $channels5g;

//        $jsonData['ssid'][] = $this->defaultSsid;

        if (isset($jsonData['snmp']['community'])) {
            $jsonData['snmp']['community'] = $this->processJsonArray($jsonData['snmp']['community']);
        }

        if (isset($jsonData['snmp']['users'])) {
            $jsonData['snmp']['users'] = $this->processJsonArray($jsonData['snmp']['users']);
        }

        $jsonData['ssid'] = $this->reindexArray($jsonData['ssid']); // Reindex array

        $jsonData = $this->convertKeys($jsonData);
        $wifiConfig = new WifiConfigurationProfiles();
        $wifiConfig->pdo_id = $user->id;
        $wifiConfig->title = $title;
        $wifiConfig->disabled = false;
        $wifiConfig->published = $request->submitType === "publish";
        $wifiConfig->settings = json_encode($jsonData);
        $wifiConfig->model_ids = json_encode($request->input('ap-model'));
        $wifiConfig->save();

        return response()->json([
            'status' => 200, 
            'message' => 'success',
            'profile_id' => $wifiConfig->id
        ]);
    }

    function reindexArray($array)
    {
        return array_values($array);
    }

    function processJsonArray($array)
    {
        $resultArray = [];
        foreach ($array as $jsonString) {
            $decodedArray = json_decode($jsonString, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                foreach ($decodedArray as $decodedObject) {
                    $resultArray[] = $decodedObject;
                }
            }
        }
        return $resultArray;
    }

    public function destroy(Request $request)
    {
        if ($request->profileId) {
            $profile = WifiConfigurationProfiles::findOrFail($request->profileId);
            $profile->delete();

            return response()->json(['success' => true, 'message' => 'Profile deleted successfully.']);

        } else {
            return response()->json(['success' => false, 'message' => 'Error deleting profile'], 500);
        }

    }

    public function disable(Request $request)
    {
        if ($request->profileId) {
            $profile = WifiConfigurationProfiles::findOrFail($request->profileId);

            $profile->disabled = false;
            $profile->save();

            return response()->json(['success' => true, 'message' => 'Profile disabled successfully.']);

        } else {
            return response()->json(['success' => false, 'message' => 'Error disabling profile'], 500);
        }

    }

    public function enable(Request $request)
    {
        if ($request->profileId) {
            $profile = WifiConfigurationProfiles::findOrFail($request->profileId);

            $profile->disabled = true;
            $profile->save();

            return response()->json(['success' => true, 'message' => 'Profile enabled successfully.']);

        } else {
            return response()->json(['success' => false, 'message' => 'Error enabling profile'], 500);
        }

    }

    public function publish(Request $request)
    {
        if ($request->profileId) {
            $profile = WifiConfigurationProfiles::findOrFail($request->profileId);

            $profile->published = true;
            $profile->save();

            // $payload = $this->getConfigurationData($profile->id, $profile->published);
            $payload = [];
            $mqtt = false;
            if (is_array($payload) && !empty($payload)) {
                $mqtt = true;
            }

            return response()->json(['success' => true, 'message' => 'Profile published successfully.', 'payload' => $payload, 'mqtt' => $mqtt]);

        } else {
            return response()->json(['success' => false, 'message' => 'Error publishing profile'], 500);
        }

    }

    public function getMacGroups()
    {

        $macGroups = MacGroups::where('pdo_id', Auth::user()->id)->get();

        return response()->json([
            'macGroups' => $macGroups,
            'message' => 'success'
        ], 200);
    }

    public function getGuests()
    {
        $guests = UserGroups::where('pdo_id', Auth::user()->id)->where('type', 'guests')->get();

        return response()->json([
            'guests' => $guests,
            'message' => 'success'
        ], 200);
    }

    public function getGroups()
    {
        $groups = UserGroups::where('pdo_id', Auth::user()->id)->where('type', 'group')->get();

        return response()->json([
            'userGroups' => $groups,
            'message' => 'success'
        ], 200);
    }

    public function getUsers()
    {
        $users = UserGroups::where('pdo_id', Auth::user()->id)->where('type', 'users')->get();

        return response()->json([
            'userGroups' => $users,
            'message' => 'success'
        ], 200);
    }

    public function edit(Request $request)
    {
        $qressid = '';
        $profile = WifiConfigurationProfiles::where('id', $request->id)->first();
        $pdoNet = PdoNetworkSetting::where('pdo_id', Auth::user()->id)->first();
        if(!isset($pdoNet))
        {
            $net = NetworkSettings::find('1')->first();
            $qressid = $net->essid;
        }else{
            $qressid = $pdoNet->essid;
        }
        // $qrCode = base64_encode(QrCode::format('png')->size(250)->generate("WIFI:T:nopass;S:$qressid;;"));
        $qrCode = "";
        return response()->json([
            'userGroups' => $profile,
            'qrCode' => $qrCode,
            'message' => 'success'
        ], 200);
    }

    public function update(Request $request)
    {
        $existingProfile = WifiConfigurationProfiles::where('id', $request->id)->first();
        $title = $request->input('title');
        $ntpServer1 = $request->input('ntp-server-1');
        $ntpServer2 = $request->input('ntp-server-2');
        $roaming = $request->input('roaming') != NULL ? 'on' : 'off';

        $snmpName = $request->input('snmp_name');
        $engineId = $request->input('engine_id');
        $contact = $request->input('contact');
        $comment = $request->input('comment');
        $cpuThreshold = $request->input('cpu_threshold');
        $memThreshold = $request->input('mem_threshold');
        $communityJson = $request->input('community_array');
        $communityArray = json_decode(json_encode($communityJson), true);
        $userJson = $request->input('user_array');
        $userArray = json_decode(json_encode($userJson), true);

        $radio2g = $request->input('radio-2g');
        $radio5g = $request->input('radio-5g');
        $zoneName = $request->input('zone_name');
        $timeZone = $request->input('time_zone');

        $uploadLimit = $request->input('upload_limit');
        $downloadLimit = $request->input('download_limit');
        $timezoneArray = [
            'zonename' => $zoneName ?? '',
            'timezone' => $timeZone ?? ''
        ];

        $timezoneFormatted = json_encode($timezoneArray);

        $jsonData = [
            'ntp_server_1' => $ntpServer1,
            'ntp_server_2' => $ntpServer2,
            'roaming' => $roaming,
            'time_zone' => $timezoneFormatted,
            'snmp' => [
                'snmp_name' => $snmpName,
                'engine_id' => $engineId,
                'contact' => $contact,
                'comment' => $comment,
                'cpu_threshold' => $cpuThreshold,
                'mem_threshold' => $memThreshold,
                'community' => $communityArray,
                'users' => $userArray,
            ],
            'radio_2g' => [
                'enabled' => $this->toBoolean($radio2g),
                'ht_mode' => $request->input('ht-mode'),
                'bandwidth' => $request->input('bandwidth'),
                'tx_power' => $request->input('tx-power'),
                'channel' => []
            ],
            'radio_5g' => [
                'enabled' => $this->toBoolean($radio5g),
                'ht_mode_5g' => $request->input('ht-mode-5g'),
                'bandwidth_5g' => $request->input('bandwidth-5g'),
                'tx_power_5g' => $request->input('tx-power-5g'),
                'channel' => []
            ],
            'ssid' => [],
            'domain_filter' => [],
            'upload_limit' => $uploadLimit,
            'download_limit' => $downloadLimit,
        ];

        $channels2g = [];
        $channels5g = [];
        $domainFilter=[];

        $j=0;
        $ssidKey = [];  
        for($i=0;$i<10;$i++){
            if( $request->input('essid-'.$i) ) {
                $ssidKey['essid-'.$i] = $j;
                $j++;
            }
        }

        foreach ($request->all() as $key => $value) {

            /** Skip PM-WANI fields completely */
            if (str_starts_with($key, 'pm-wani') || str_starts_with($key, 'pm_wani')) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            
            // if (preg_match('/^channel(?:-5g)?-(\d+)$/', $key, $matches)) {
            //     $channelNumber = (int)$matches[1];
            //     if (strpos($key, 'channel-5g') === 0) {
            //         $channels5g[] = $channelNumber;
            //     } else {
            //         $channels2g[] = $channelNumber;
            //     }
            // } 
            if (preg_match('/^channel(?:-5g)?$/', $key, $matches)) {
                $key = trim(strtolower($key));
                if ($key === 'channel-5g') {
                    $channels5g[] = $value;
                } elseif ($key === 'channel') {
                    $channels2g[] = $value;
                }
            }
            elseif (preg_match('/^(whitelist|blacklist)_ip_(\d+)$/', $key, $matches)) {
                $type = $matches[1];
                $suffix = (int)$matches[2];

                if (!in_array($suffix, ['channel', 'channel-5g', 'ntp-server-1', 'ntp-server-2', 'ntp-server'])) {

                    if (!isset($domainFilter[$suffix])) {
                        $domainFilter[$suffix] = [];
                    }

                    $domainFilter[$suffix][$type . '_ip'] = $value;
                }
            } elseif (preg_match('/^(.*?)-(\d+)$/', $key, $matches)) {
                $field = $matches[1];
                $suffix = $matches[2];

                // if (!in_array($field, ['channel', 'channel-5g', 'ntp-server-1', 'ntp-server-2', 'ntp-server'])) {
                //     if (!isset($jsonData['ssid'][$suffix - 1])) {
                //         $jsonData['ssid'][$suffix - 1] = [];
                //     }
                //     if ($value === null || $value === '') {
                //         continue;
                //     }
                //     $jsonData['ssid'][$suffix - 1][$field] = $this->toBoolean($value);
                //     $jsonData['ssid'][$suffix - 1]['mode'] = 'ap';
                // }

                if (!in_array($field, ['channel', 'channel-5g', 'ntp-server-1', 'ntp-server-2', 'ntp-server'])) {
                    $jsonData['ssid'][$ssidKey['essid-'.$suffix]][$field] = $this->toBoolean($value);
                    $jsonData['ssid'][$ssidKey['essid-'.$suffix]]['mode'] = 'ap';
                }
            }
        }
        $existingProfile->model_ids = json_encode($request->input('ap-model'));

        $whitelistIp = [];
        $blacklistIp = [];

        foreach ($domainFilter as $item) {
            if (isset($item['whitelist_ip']) && !empty($item['whitelist_ip'])) {
                $whitelistIp[] = $item['whitelist_ip'];
            }
            if (isset($item['blacklist_ip']) && !empty($item['blacklist_ip'])) {
                $blacklistIp[] = $item['blacklist_ip'];
            }
        }

        $domainFilter = [
            'whitelist_ip' => $whitelistIp,
            'blacklist_ip' => $blacklistIp
        ];

        $jsonData['domain_filter'] = $domainFilter;

        $jsonData['radio_2g']['channel'] = $channels2g;
        $jsonData['radio_5g']['channel'] = $channels5g;

//        $jsonData['ssid'][] = $this->defaultSsid;

        if (isset($jsonData['snmp']['community'])) {
            $jsonData['snmp']['community'] = $this->processJsonArray($jsonData['snmp']['community']);
        }

        if (isset($jsonData['snmp']['users'])) {
            $jsonData['snmp']['users'] = $this->processJsonArray($jsonData['snmp']['users']);
        }

        if(!$jsonData['ssid']){
            $jsonData['ssid'] = $this->reindexArray($jsonData['ssid']);
        }
        
        $jsonData = $this->convertKeys($jsonData);

        $existingProfile->pdo_id = Auth::user()->id;
        $existingProfile->title = $title;
        $existingProfile->disabled = false;
        $existingProfile->published = $request->submitType === "publish";
        $existingProfile->settings = json_encode($jsonData);
        $existingProfile->save();

        // $payload = $this->getConfigurationData($existingProfile->id, $existingProfile->published);
        $payload = [];
        $mqtt = false;
        if (is_array($payload) && !empty($payload)) {
            $mqtt = true;
        }

        $locations = Location::where('wifi_configuration_profile_id', $existingProfile->id)->get(); 
        if ($locations && $existingProfile->published == true) {
            foreach ($locations as $location) {
                $routers = Router::where('location_id', $location->id)->get();
                foreach ($routers as $router) {
                    $router->last_configuration_version = $router->configurationVersion;
                    $router->last_updated_at = $router->updated_at;
                    $router->increment('configurationVersion');
                    $router->save();
                }
            }
        }
        return response()->json(['status' => 200, 'message' => 'Success','payload' => $payload, 'mqtt' => $mqtt]);
    }



    public function wifiSettings()
    {
        $user = Auth::user();
        $wifiSettings = WifiConfigurationProfiles::where('pdo_id', $user->id)->where('published', true)->get();

        if ($wifiSettings) {

            return response()->json(['wifi_settings' => $wifiSettings], 200);
        } else {
            return response()->json(['wifi_settings' => ''], 404);
        }
    }

    public function getConfigurationData($profile_id, $profile_published){
        $locations = Location::where('wifi_configuration_profile_id', $profile_id)->get();
        $payload = [];
        $payloaddata = [];
        
        if ($locations && $profile_published == true) {            
            foreach ($locations as $location) {
                $routers = Router::where('location_id', $location->id)->get();
                foreach ($routers as $router) {
                    $router->last_configuration_version = $router->configurationVersion;
                    $router->last_updated_at = $router->updated_at;
                    $router->increment('configurationVersion');
                    $router->save(); 

                    $key = $router->key ?? null;
                    $secret = $router->secret ?? null;
                    if ($key && $secret) {
                        // $controller = app(\App\Http\Controllers\WiFRouterController::class);
                        // $originalData = json_decode(json_encode($controller->config_new($key, $secret)->getData()), true);
                        // // Later in your code:
                        $controller = app(WiFRouterController::class);
                        $response = $controller->config_new($key, $secret);

                        // If it's a JSON response, get the actual data:
                        $originalData = json_decode(json_encode($response->getData()), true);
                        $timezone= [
                            'time_zone' => $originalData['config']['time_zone'],
                            'zone_name' => $originalData['config']['zone_name']
                        ];
                        unset($originalData['config']['timezone']);
                        unset($originalData['config']['time_zone']);
                        unset($originalData['config']['zone_name']);
                        unset($originalData['config']['device_dns']);
                        unset($originalData['config']['domain']);
                        unset($originalData['config']['log_server_url']);
                        unset($originalData['config']['reset']);
                        unset($originalData['config']['restart']);
                        unset($originalData['config']['status']);
                        unset($originalData['config']['update_file_hash']);
                        unset($originalData['config']['update_file']);
                        unset($originalData['config']['location_id']);
                        $originalData['config']['timezone'] = $timezone;
                        $originalData['config']['mac'] = $router->mac_address;
                        $payload [] = $originalData['config'];
                    }
                }
            }
            if (isset($payload) && is_array($payload)) {
                $payloaddata['devices'] = $payload;
            }
        }
        return $payloaddata;
    }

    public function savePmWaniSsidControl(Request $request)
    {
        try {
            // Get the profile ID from request
            $profileId = $request->input('profile_id');
            if (!$profileId) {
                return response()->json(['status' => 400, 'message' => 'Profile ID is required'], 400);
            }

            // Find the existing profile
            $profile = WifiConfigurationProfiles::findOrFail($profileId);

            // Extract PM WANI settings from request
            $pmWaniData = [
                'essid' => $request->input('pm_wani_essid'),
                'subnet_mask' => $request->input('pm_wani_subnet_mask'),
                'lease_time' => $request->input('pm_wani_lease_time'),
                'advance_setting' => $request->input('pm_wani_advance_setting')
            ];

            // Save individual columns
            $profile->essid = $pmWaniData['essid'];
            $profile->subnet_mask = $pmWaniData['subnet_mask'];
            $profile->lease_time = $pmWaniData['lease_time'] ? (int)$pmWaniData['lease_time'] : null;

            // Save advance_setting as JSON in the advance_setting column
            if ($pmWaniData['advance_setting']) {
                $profile->advance_setting = json_encode($pmWaniData['advance_setting']);
            }

            $profile->save();

            return response()->json([
                'status' => 200,
                'message' => 'PM WANI SSID Settings saved successfully',
                'data' => $pmWaniData
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving PM WANI settings: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error saving PM WANI settings: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPmWaniSsidControl(Request $request)
    {
        try {
            $profileId = $request->input('profile_id');
            if (!$profileId) {
                return response()->json(['status' => 400, 'message' => 'Profile ID is required'], 400);
            }

            $profile = WifiConfigurationProfiles::findOrFail($profileId);

            // Decode advance_setting JSON if it exists
            $advanceSetting = $profile->advance_setting ? json_decode($profile->advance_setting, true) : null;

            $pmWaniData = [
                'essid' => $profile->essid,
                'subnet_mask' => $profile->subnet_mask,
                'lease_time' => $profile->lease_time,
                'advance_setting' => $advanceSetting
            ];

            return response()->json([
                'status' => 200,
                'message' => 'PM WANI SSID Settings retrieved successfully',
                'data' => $pmWaniData
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving PM WANI settings: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving PM WANI settings: ' . $e->getMessage()
            ], 500);
        }
    }
}
