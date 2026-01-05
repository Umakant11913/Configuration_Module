<?php

namespace App\Http\Controllers;

use App\Events\UserIPAccessLogEvent;
use App\Models\BrandingProfile;
use App\Models\MacGroups;
use App\Models\MessageLog;
use App\Models\KernelLog;
use App\Models\ModelFirmwares;
use App\Models\Models;
use App\Models\PdoNetworkSetting;
use App\Models\Router;
use App\Models\UserIPAccessLog;
use App\Models\NetworkSettings;
use App\Models\Location;
use App\Models\WifiConfigurationProfiles;
use App\Models\WiFiStatus;
use http\Env;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use \App\Models\MqttApsLiveStatus;
use App\Models\MqttApsResponse;
use App\Models\NetworkProfile;

class WiFRouterController extends Controller

{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['heartbeat', 'config', 'config_new', 'verify', 'wifilogin','verify_router','ip_logging', 'latest_version', 'reset', 'reboot', 'ssid_configuration', 'ip_logging_kernel', 'custom_result']]);
    }


    public function index($id)
    {
        $wifiRouter = Router::where('id', $id)->get();
        if ($wifiRouter) {
            return response()->json([
                'status' => true,
                'message' => 'WiFi Router Details retrived',
                'wifiRouter' => $wifiRouter
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'WiFi Router Does not exists',
            ], 401);
        }
    }

    public function list()
    {
        $wifiRouters = Router::get();
        if ($wifiRouters) {
            return response()->json([
                'status' => true,
                'message' => 'WiFi Router List retrived',
                'wifiRouters' => $wifiRouters
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'WiFi Router List not retrived',
            ], 401);
        }
    }

    public function create(Request $request)
    {
        $key = Str::random(20);
        $secret = Str::random(30);

        $wifiCreateRequest = array();
        $wifiCreateRequest['key'] = $key;
        $wifiCreateRequest['secret'] = $secret;
        $wifiCreateRequest['macAddress'] = strtoupper($request->macAddress);
        $wifiCreateRequest['status'] = 1;

        $validator = Validator::make($request->all(), [
            'modelNumber' => 'max:100',
            'macAddress' => 'required|string|between:17,18',
            'serialNumber' => 'max:50',
            'name' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $wifiRouter = Router::create(array_merge(
            $validator->validated(), $wifiCreateRequest
        ));

        return response()->json([
            'message' => 'WiFi Router successfully created',
            'wifiRouter' => $wifiRouter
        ], 201);
    }
    
    function getTxPowerDbm($percent, $maxDbm)
    {
        if ($percent === "Auto") {
            return "auto";
        }

        return round($maxDbm * ($percent / 100));
    }
    
    public function config_new($key, $secret)
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";

        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();

        $networkSettings = NetworkSettings::where('id',1)->first();
        $locationController = Location::where('id',$wifiRouter->location_id)->first();

        $domain = $networkSettings->loginUrl;

        $defaultWhitelist = "bsnl.pmwani.net,staging.pmwani.net,pdo.pmwani.net,wifiadmin.immunitynetworks.com,demo.immunitynetworks.com,demo-login.immunitynetworks.com,demo-api.immunitynetworks.com,portal2.bsnl.in,pgi.billdesk.com";
        $defaultDomainWhitelist = ".immunitynetworks.com,.pmwani.net,.bsnl.in,.onlinesbi.sbi,.onlinesbi.com,.sbi.co.in,.sbicard.com,.akamaihd.net,.cloudflare.com,.cloudfront.net,.adobedtm.com,.billdesk.com,.googletagmanager.com,.google-analytics.com";

        // Environment whitelist and domain whitelist values
        $envWhitelist = env('WHITELIST', '');
        $envDomainWhitelist = env('DOMAIN_WHITELIST', '');

        // Convert the strings to arrays
        $defaultWhitelistArray = array_map('trim', explode(',', $defaultWhitelist));
        $defaultDomainWhitelistArray = array_map('trim', explode(',', $defaultDomainWhitelist));

        $envWhitelistArray = $envWhitelist ? array_map('trim', explode(',', $envWhitelist)) : [];
        $envDomainWhitelistArray = $envDomainWhitelist ? array_map('trim', explode(',', $envDomainWhitelist)) : [];

        // Initialize arrays for URLs from profile
        $profileWhitelistArray = [];
        $profileDomainWhitelistArray = [];

        if (isset($locationController) && $locationController->profile_id !== NULL) {
            $zoneProfile = BrandingProfile::where('id', $locationController->profile_id)->first();
            // Extract URLs from the profile
            $urlsToAdd = [];

            $bannerRedirectUrl = isset($zoneProfile->banner_url) ? parse_url($zoneProfile->banner_url) : '';
            $privacyPolicyUrl = isset($zoneProfile->privacy_policy) ? parse_url($zoneProfile->privacy_policy) : '';
            $termsConditionsUrl = isset($zoneProfile->terms_conditions) ? parse_url($zoneProfile->terms_conditions) : '';
            $whitelistedUrlJson = isset($zoneProfile->whitelisted_urls) ? json_decode($zoneProfile->whitelisted_urls, true) : '';

            if ($bannerRedirectUrl && isset($bannerRedirectUrl['scheme']) && $bannerRedirectUrl['scheme'] === 'https' && isset($bannerRedirectUrl['host'])) {
                $urlsToAdd[] = $bannerRedirectUrl['host'];
            }
            if ($privacyPolicyUrl && isset($privacyPolicyUrl['scheme']) && $privacyPolicyUrl['scheme'] === 'https' && isset($privacyPolicyUrl['host'])) {
                $urlsToAdd[] = $privacyPolicyUrl['host'];
            }
            if ($termsConditionsUrl && isset($termsConditionsUrl['scheme']) && $termsConditionsUrl['scheme'] === 'https' && isset($termsConditionsUrl['host'])) {
                $urlsToAdd[] = $termsConditionsUrl['host'];
            }
            if ($whitelistedUrlJson && isset($whitelistedUrlJson['urls']) && is_array($whitelistedUrlJson['urls'])) {
                foreach ($whitelistedUrlJson['urls'] as $whitelistedUrl) {
                    $parsedWhitelistedUrl = parse_url($whitelistedUrl);
                    if (isset($parsedWhitelistedUrl['host'])) {
                        $urlsToAdd[] = $parsedWhitelistedUrl['host'];
                    } elseif (isset($parsedWhitelistedUrl['path'])) {
                        $urlsToAdd[] = $parsedWhitelistedUrl['path'];
                    }
                }
            }

            // Separate URLs into profile whitelist and domain whitelist
            foreach ($urlsToAdd as $urlToAdd) {
                if (strpos($urlToAdd, '*') === 0) {
                    $profileDomainWhitelistArray[] = substr($urlToAdd, 1);
                } else {
                    $profileWhitelistArray[] = $urlToAdd;
                }
            }
        }

        // Merge all whitelist arrays
        $whitelistArray = array_unique(array_merge($defaultWhitelistArray, $envWhitelistArray, $profileWhitelistArray));
        $domainWhitelistArray = array_unique(array_merge($defaultDomainWhitelistArray, $envDomainWhitelistArray, $profileDomainWhitelistArray));

        $whitelist = array_filter(array_unique($whitelistArray), fn($url) => !empty($url));
        $domainWhitelist = array_filter(array_unique($domainWhitelistArray), fn($url) => !empty($url));

        $whitelist = array_values($whitelist);
        $domainWhitelist = array_values($domainWhitelist);

        $wifiLocationConfigurationId = "";
        $NetworkProfileStatus = "";
        $NetworkProfile = "";
        $snmpProfileStatus = "";
        $snmpProfile = "";
        $ntpProfile = "";
        $qosProfile = "";
        $domainFilterProfile = "";
        $syslogProfileData = "";
        $syslogProfileStatus = "";
        //new code
        if($wifiRouter) {
            $wifiLocation = Location::where('id', $wifiRouter->location_id)->first();
            if($wifiLocation && isset($wifiLocation->wifi_configuration_profile_id)) {
                $wifiLocationConfigurationId = $wifiLocation->wifi_configuration_profile_id;
            }
            if($wifiLocation && isset($wifiLocation->network_profile_id)) { // network profile setting
                $NetworkProfileData = NetworkProfile::where('id', $wifiLocation->network_profile_id)->first();
                if($NetworkProfileData){
                    $NetworkProfileStatus = $NetworkProfileData['status'];
                    $NetworkProfile = $NetworkProfileData['content'];
                }
            }
            if($wifiLocation && isset($wifiLocation->snmp_profile_id)) { // snmp profile setting
                $snmpProfileData = NetworkProfile::where('id', $wifiLocation->snmp_profile_id)->first();
                if($snmpProfileData){
                    $snmpProfileStatus = $snmpProfileData['status'];
                    $snmpProfile = $snmpProfileData['content'];
                }
            }
            if($wifiLocation && isset($wifiLocation->ntp_profile_id)) { // ntp profile setting
                $ntpProfileData = NetworkProfile::where('id', $wifiLocation->ntp_profile_id)->first();
                if($ntpProfileData){
                    $ntpProfile = $ntpProfileData['content'];
                }
            }
            if($wifiLocation && isset($wifiLocation->qos_profile_id)) { // qos profile setting
                $qosProfileData = NetworkProfile::where('id', $wifiLocation->qos_profile_id)->first();
                if($qosProfileData){
                    $qosProfile = $qosProfileData['content'];
                }
            }
            if($wifiLocation && isset($wifiLocation->domainfilter_profile_id)) { // Domain fileter setting
                $domainFilterProfileData = NetworkProfile::where('id', $wifiLocation->domainfilter_profile_id)->first();
                if($domainFilterProfileData){
                    $domainFilterProfile = $domainFilterProfileData['content'];
                }
            }
            if($wifiLocation && isset($wifiLocation->syslog_profile_id)) { // syslog_ng setting
                $syslogProfileData = NetworkProfile::where('id', $wifiLocation->syslog_profile_id)->first();
                if($syslogProfileData){
                    $syslogProfileStatus = $syslogProfileData['status'];
                }
            }
            
        } else {
            return response()->json([
                "status" => false,
                "message" => "WiFi Router Config retrival failed",
            ], 401);
        }

        $config = array();
        $config["radios"] = null;
        $config["ssids"] = [];
        $config["ntp_server"] = null;
        $config["roaming"] = null;
        $config["snmp"] = null;
        $config["timezone"] = "1";
        $config["zone_name"] = "Asia/Kolkata";
        $config["time_zone"] = "IST-5:30";
        $config["download_limit"] = ceil(($wifiRouter->download_speed) * .90) * 1000;
        $config["upload_limit"] = ceil(($wifiRouter->upload_speed) * .90) * 1000;
        $config["ip_blacklist"] = null;
        $config["domain_blacklist"] = null;
        $config["domain_whitelist"] = null;
        $config["ip_whitelist"] = null;

        //id PDO has settup the default ESSID name
        $pdoEssid = "";        
        $lease_time = 1;
        $pmwaniDisabled = false;
        if (isset($locationController) && $locationController->profile_id !== NULL) {
            $zoneProfile = BrandingProfile::where('id', $locationController->profile_id)->first();
            $pdoNetworkSetting = PdoNetworkSetting::where('pdo_id', $zoneProfile->pdo_id)->first();
            if ($pdoNetworkSetting && $pdoNetworkSetting->essid !== null) {
                $pdoEssid = $pdoNetworkSetting->essid;
                $advance_setting = json_decode($pdoNetworkSetting->advance_setting, true);
            }
            if ($zoneProfile && $zoneProfile->default_plans == 0) {
                $pmwaniDisabled = true;
            }
        }
        
        if($wifiLocationConfigurationId) {
            $pmWaniSettingPDO = WifiConfigurationProfiles::where("id", $wifiLocationConfigurationId)->first();
            if (isset($pmWaniSettingPDO) && isset($pmWaniSettingPDO->advance_setting) && !is_null($pmWaniSettingPDO->advance_setting)) {
                $advance_setting = json_decode($pmWaniSettingPDO->advance_setting, true); 
            }

            if (isset($pmWaniSettingPDO) && isset($pmWaniSettingPDO->essid) && !is_null($pmWaniSettingPDO->essid)) {
                $pdoEssid = $pmWaniSettingPDO->essid;
            }
    
            if (isset($pmWaniSettingPDO) && isset($pmWaniSettingPDO->subnet_mask) && !is_null($pmWaniSettingPDO->subnet_mask)) {
                $lease_time = $pmWaniSettingPDO->lease_time ?? 1;
                $subnet_mask = $pmWaniSettingPDO->subnet_mask ?? 22;
            } else {
                $lease_time = $pdoNetworkSetting->lease_time ?? 1;
                $subnet_mask = $pdoNetworkSetting->subnet_mask ?? 22;
            }
        }

        $leasetime = (($lease_time * 60) * 60);
        
        $network_type = "";
        $vlan = "";
        if($NetworkProfile && isset($NetworkProfile)){
            if($NetworkProfile['network-type'] == "wan"){
                $network_type = "wan";
                if (isset($NetworkProfile['vlan'])) {
                    $vlan = $NetworkProfile['vlan'];
                } 
            }
        }
        //Pmwani default SSID & Immunity Support SSID       
        $pmwaniSsid = [
            "network_type" => $network_type == "wan" ? "wan" : "hotspot-pw",
            "essid" => $pdoEssid !== "" ? $pdoEssid : $networkSettings->essid,
            "security" => "none",
            "hidden" => false,
            "mode" => "ap",
            "disabled" => false,
            "radios" => ["2g", "5g"],
            "radius_ip" => $networkSettings->radiusServer ?? "",
            "radius_secret" => $networkSettings->radiusSecret ?? "",
            "whitelist" => $whitelist ?? "",
            "whitelist_domains" => $domainWhitelist ?? "",
            "login_url" => "https://" . $networkSettings->loginUrl,
            "nasid" => $wifiRouter->location_id,            
            "subnet_mask" => $subnet_mask ?? 22,
            "lease_time" => $leasetime,
            "wifiStability" => true,
            "vlan" => $vlan ?? ''
        ];
        // Merge $advance_setting and $pmwaniSsid arrays, with $pmwaniSsid values taking precedence in case of key conflicts
        if (isset($advance_setting) && is_array($advance_setting)) {
            $pmwaniSsid = array_merge($pmwaniSsid, $advance_setting);
        }

        $mac = str_replace('-', '', $wifiRouter->macAddress);
        $mac_address_last_digits = substr($mac, -6);
        $networkSettings->support_essid .= '-' . $mac_address_last_digits;

        $pmwaniSupportSsid = [];
        if (isset($networkSettings->support_essid)) {
            $pmwaniSupportSsid = [
                "network_type" => "support",
                "essid" => $networkSettings->support_essid,
                "security" => "WPA2-Personal",
                "hidden" => !($networkSettings->support_essid_hide == 0),
                "password" => $networkSettings->support_essid_password,
                "mode" => "ap",
                "disabled" => false,
                "maxassoc" => 100,
                "radios" => ["2g", "5g"]
            ];
        }
        if($wifiLocationConfigurationId) {
            $confugrationProfile = WifiConfigurationProfiles::where("id", $wifiLocationConfigurationId)->first();
        }
        
        if($wifiLocationConfigurationId && isset($confugrationProfile->settings)) { // if not set the configuration profile check it
            // $confugrationProfile = WifiConfigurationProfiles::where("id", $wifiLocationConfigurationId)->first();
            $data = json_decode($confugrationProfile->settings, true);

            if (!empty($data["time_zone"])) {
                $timeZoneData = json_decode($data["time_zone"], true);

                if ($timeZoneData !== null) {
                    $config["zone_name"] = $timeZoneData["zonename"];
                    $config["time_zone"] = $timeZoneData["timezone"];
                }
            }

            if (!empty($qosProfile)) { // download and upload speed profilw is set then collect the data
                if (!empty($qosProfile['download_limit']) && $qosProfile['download_limit'] !== "0") {
                    $config['download_limit'] = ceil($qosProfile['download_limit'] * .90) * 1000;
                }
                if (!empty($qosProfile['upload_limit']) && $qosProfile['upload_limit'] !== "0") {
                    $config['upload_limit'] = ceil($qosProfile['upload_limit'] * .90) * 1000;
                }
            } else {
                if ((!empty($data["download_limit"]) && $data["download_limit"] !== "0") ||
                    (!empty($data["upload_limit"]) && $data["upload_limit"] !== "0")) {
                    $config["download_limit"] = ceil($data["download_limit"] * .90) * 1000;
                    $config["upload_limit"] = ceil($data["upload_limit"] * .90) * 1000;
                }
            }

            // for radios
            $radio_2g_data = $data["radio_2g"];
            $radio_5g_data = $data["radio_5g"];            
            
            /* set txPower of the 2G network */
            $max_dbm_2g = 20; //2.4G max = 20
            $configTxPower2g = $this->getTxPowerDbm($radio_2g_data['tx_power'], $max_dbm_2g);

            /* set txPower of the 5G network */
            $max_dbm_5g = 23; //5G max = 23
            $configTxPower5g = $this->getTxPowerDbm($radio_5g_data['tx_power_5g'], $max_dbm_5g);

            /* set htmode of the 2G network */
            $map = [
                "802.11b"                  => ["20MHz" => "NOHT"],
                "802.11a/b"                => ["20MHz" => "HT20", "40MHz" => "HT40"],
                "802.11a/b/g"              => ["20MHz" => "HT20", "40MHz" => "HT40"],
                "802.11a/b/g/n"            => ["20MHz" => "HT20", "40MHz" => "HT40"],
                "802.11a/b/g/n/ac/ax"      => ["20MHz" => "HE20", "40MHz" => "HE40"], // adjust as needed
            ];            
            $htmode_2g = "HT40"; // default
            if (isset($radio_2g_data["ht_mode"]) && isset($radio_2g_data["bandwidth"])) {
                $mode_2g  = $radio_2g_data["ht_mode"];
                $width_2g = $radio_2g_data["bandwidth"];
                if (isset($map[$mode_2g][$width_2g])) {
                    $htmode_2g = $map[$mode_2g][$width_2g];
                }
            }

            /* set htmode of the 5G network */
            $map_5g = [
                "802.11a"             => ["20MHz" => "NOHT", "40MHz" => "HT40"  ],            
                "802.11a/b"           => ["20MHz" => "HT20", "40MHz" => "HT40", "80MHz" => "HT40", "160MHz" => "HT40",],            
                "802.11a/b/g"         => ["20MHz" => "HT20", "40MHz" => "HT40", "80MHz" => "HT40", "160MHz" => "HT40",],            
                "802.11a/b/g/n"       => ["20MHz" => "HT20", "40MHz" => "HT40", "80MHz" => "HT40", "160MHz" => "HT40",],            
                "802.11a/b/g/n/ac"    => ["20MHz" => "VHT20", "40MHz" => "VHT40", "80MHz" => "VHT80", "160MHz" => "VHT160",],            
                "802.11a/b/g/n/ac/ax" => ["20MHz" => "HE20", "40MHz" => "HE40", "80MHz" => "HE80", "160MHz" => "HE160",],
            ];
            $htmode_5g = "VHT80"; // default
            if (isset($radio_5g_data["ht_mode_5g"]) && isset($radio_5g_data["bandwidth_5g"])) {
                $mode_5g  = $radio_5g_data["ht_mode_5g"];
                $width_5g = $radio_5g_data["bandwidth_5g"];
                if (isset($map_5g[$mode_5g][$width_5g])) {
                    $htmode_5g = $map_5g[$mode_5g][$width_5g];
                }
            }

            /* set the disable of the 2G network */
            $disabled_2g = !($radio_2g_data["enabled"] ?? false);
            /* set the disable of the 5G network */
            $disabled_5g = !($radio_5g_data["enabled"] ?? false);
            
            /* set the channel of the 2G network */
            $channel_2g = (isset($radio_2g_data["channel"]) && is_array($radio_2g_data["channel"]) && !empty($radio_2g_data["channel"])) ? implode(',', $radio_2g_data["channel"]) : 'auto';
            /* set the channel of the 5G network */
            $channel_5g = (isset($radio_5g_data["channel"]) && is_array($radio_5g_data["channel"]) && !empty($radio_5g_data["channel"])) ? implode(',', $radio_5g_data["channel"]) : '36';
            $radiosArray = [
                [
                    "band" => "2g",
                    "htmode" => $htmode_2g, //"HT40",
                    "channel" => $channel_2g,
                    "disabled" => $disabled_2g,                   
                    "txpower" => $configTxPower2g,
                ],
                [
                    "band" => "5g",
                    "htmode" => $htmode_5g,
                    "channel" => $channel_5g,
                    "disabled" => $disabled_5g,
                    "txpower" => $configTxPower5g
                ]
            ];

            /*$radiosEnabled = [];
            foreach ($data["ssid"] as $ssid) {
                if ($ssid["radio_2g"] || $ssid["radio_5g"]) {
                    $radiosEnabled[] = "2g";
                    $radiosEnabled[] = "5g";
                    break; // Break the loop once any SSID with enabled radios is found
                }
            }

            $radiosEnabled = [];
            foreach ($data["ssid"] as $ssid) {
                if (isset($ssid["radio_2g"]) || isset($ssid["radio_5g"])) {
                    if (!empty($ssid["radio_2g"])) {
                        $radiosEnabled[] = "2g";
                    }
                    if (!empty($ssid["radio_5g"])) {
                        $radiosEnabled[] = "5g";
                    }
                    break; // Break the loop once any SSID with enabled radios is found
                }
            }*/

            if (isset($data["ssid"]) && is_array($data["ssid"])) {
                foreach ($data["ssid"] as $ssid) {
                    // Check if 'radio_2g' and 'radio_5g' exist and are not null
                    $radio2gEnabled = isset($ssid["radio_2g"]) && $ssid["radio_2g"];
                    $radio5gEnabled = isset($ssid["radio_5g"]) && $ssid["radio_5g"];

                    if ($radio2gEnabled || $radio5gEnabled) {
                        if ($radio2gEnabled) {
                            $radiosEnabled[] = "2g";
                        }
                        if ($radio5gEnabled) {
                            $radiosEnabled[] = "5g";
                        }
                        break; // Break the loop once any SSID with enabled radios is found
                    }
                }
            }

            /*foreach ($data["ssid"] as $ssid) {
                if ((isset($ssid["radio_2g"]) && $ssid["radio_2g"]) || (isset($ssid["radio_5g"]) && $ssid["radio_5g"])) {
                    if (isset($ssid["radio_2g"]) && $ssid["radio_2g"]) {
                        $radiosEnabled[] = "2g";
                    }
                    if (isset($ssid["radio_5g"]) && $ssid["radio_5g"]) {
                        $radiosEnabled[] = "5g";
                    }
                    break; // Break the loop once any SSID with enabled radios is found
                }
            }*/


            foreach ($data["ssid"] as &$ssid) {
                $ssid["radios"] = $radiosEnabled;
                unset($ssid["radio_2g"]);
                unset($ssid["radio_5g"]);

                if (isset($ssid["ssid"])) {
                    $ssid["essid"] = $ssid["ssid"];
                    unset($ssid["ssid"]);
                }

                if (isset($ssid["network_type"]) && ($ssid["network_type"] === "hotspot-pw" || $ssid["network_type"] === "hotspot-cp")) {
                    $ssid["nasid"] = $wifiRouter->location_id ?? ""; // Adjust this line if you have a specific 'nasid' value
                }

                if (!isset($ssid["disabled"])) {
                    $ssid["disabled"] = false;
                }
                if (!isset($ssid["hidden"])) {
                    $ssid["hidden"] = false;
                }

                $ssid["mac_ids"] = null;
                $ssid["mac_filter"] = "off";

                if (isset($ssid["mac_auth"]) && $ssid["mac_auth"] != "null") {
                    $macAuthId = $ssid["mac_auth"];
                    $macGroup = MacGroups::find($macAuthId);

                    if ($macGroup) {
                        $macAddresses = json_decode($macGroup->mac_address, true);

                        $macAddresses = array_map(function($mac) {
                            if (strpos($mac, '-') !== false) {
                                $mac = str_replace('-', ':', $mac);
                            }
                            return $mac;
                        }, $macAddresses);

                        $macAddressesString = implode(",", $macAddresses);
                        $macAddressesString = str_replace(", ", ",", $macAddressesString);

                        $ssid["mac_ids"] = $macAddressesString;
                        $ssid["mac_filter"] = "on";
                    }
                }

                if (isset($ssid["network_type"]) && $ssid["network_type"] === "hotspot-cp") {
                    $existingWhitelist = isset($ssid["whitelist"]) && $ssid["whitelist"] !== "" ? explode(',', $ssid["whitelist"]) : [];
                    $mergedWhitelist = array_unique(array_merge($existingWhitelist, $whitelist));
                    $ssid["whitelist"] = $mergedWhitelist;

                    $existingWhitelistDomains = isset($ssid["whitelist_domain"]) && $ssid["whitelist_domain"] !== "" ? explode(',', $ssid["whitelist_domain"]) : [];
                    $mergedWhitelistDomains = array_unique(array_merge($existingWhitelistDomains, $domainWhitelist));
                    $ssid["whitelist_domain"] = $mergedWhitelistDomains;

                    $ssid["login_url"] = env('CUSTOM_RADIUS_LOGIN_URL') . '?ssid=' . $ssid["essid"];

                    if(isset($ssid["subnet_mask"])){
                        $ssid["subnet_mask"] = $ssid["subnet_mask"];
                    }

                    if(isset($ssid["lease_time"])){
                        $ssid["lease_time"] = (($ssid["lease_time"] * 60) * 60);
                    }

                    if(isset($ssid["leasetime"])) {
                        $ssid["lease_time"] = (($ssid["leasetime"] * 60) * 60);
                        unset($ssid["leasetime"]);
                    }

                }

                if (isset($ssid["network_type"]) && $ssid["network_type"] === "support") {
                    $mac = str_replace('-', '', $wifiRouter->macAddress);
                    $mac_address_last_digits = substr($mac, -6);
                    $ssid["essid"] .= '-' . $mac_address_last_digits;
                }

                if(isset($NetworkProfile)){
                    if($NetworkProfile['network-type'] == "wan"){
                        $ssid["network_type"] = "wan";
                        $ssid["vlan"] = $NetworkProfile['vlan'];
                    }
                }

            }

            $ip_blacklist = [];
            $domain_blacklist = [];
            $ip_whitelist = [];
            $domain_whitelist = [];
            if(!empty($domainFilterProfile)){
                
                if (!empty($domainFilterProfile['whitelist_ip'])) {
                    foreach ($domainFilterProfile['whitelist_ip'] as $entry) {
                        if (filter_var($entry, FILTER_VALIDATE_IP)) {
                            $ip_whitelist[] = $entry;
                        } else {
                            $domain_whitelist[] = $entry;
                        }
                    }
    
                    $config["ip_whitelist"] = implode(',', $ip_whitelist);
                    $config["domain_whitelist"] = implode(',', $domain_whitelist);
                }
                if (!empty($domainFilterProfile['blacklist_ip'])) {
                    foreach ($domainFilterProfile['blacklist_ip'] as $entry) {
                        if (filter_var($entry, FILTER_VALIDATE_IP)) {
                            $ip_blacklist[] = $entry;
                        } else {
                            $domain_blacklist[] = $entry;
                        }
                    }
    
                    $config["ip_blacklist"] = implode(',', $ip_blacklist);
                    $config["domain_blacklist"] = implode(',', $domain_blacklist);
                }
            } else {
                if (!empty($data['domain_filter']['blacklist_ip'])) {
                    foreach ($data['domain_filter']['blacklist_ip'] as $entry) {
                        if (filter_var($entry, FILTER_VALIDATE_IP)) {
                            $ip_blacklist[] = $entry;
                        } else {
                            $domain_blacklist[] = $entry;
                        }
                    }
    
                    $config["ip_blacklist"] = implode(',', $ip_blacklist);
                    $config["domain_blacklist"] = implode(',', $domain_blacklist);
                }
                if (!empty($data['domain_filter']['whitelist_ip'])) {
                    foreach ($data['domain_filter']['whitelist_ip'] as $entry) {
                        if (filter_var($entry, FILTER_VALIDATE_IP)) {
                            $ip_whitelist[] = $entry;
                        } else {
                            $domain_whitelist[] = $entry;
                        }
                    }
    
                    $config["ip_whitelist"] = implode(',', $ip_whitelist);
                    $config["domain_whitelist"] = implode(',', $domain_whitelist);
                }
            }



            /*if ($pmwaniDisabled !== true) {
                $data["ssid"][] = $pmwaniSsid;
            }*/

            $data["ssid"][] = $pmwaniSsid;
            $data["ssid"][] = $pmwaniSupportSsid;

            $ntp_servers = [];
            if (!empty($ntpProfile) && is_array($ntpProfile)) { // ntp profilw is set then collect the data 
                unset($ntpProfile['default_ntp_server']);
                foreach ($ntpProfile as $key => $value) {
                    if (!empty($value) && $key !== 'default_ntp_server') {
                        $ntp_servers[] = $value;
                    }
                }
            } else {
                if (!empty($data["ntp_server_1"])) {
                    $ntp_servers[] = $data["ntp_server_1"];
                }
                if (!empty($data["ntp_server_2"])) {
                    $ntp_servers[] = $data["ntp_server_2"];
                }
            }

            if(count($ntp_servers) > 0) {
                $ntpServersString = implode(",", $ntp_servers);
            }
            
            if (!empty($snmpProfile) && is_array($snmpProfile)) {
                $data["snmp"] = $snmpProfile;
            }
            
            $config["radios"] = $radiosArray ?? [];
            $config["ssids"] = $data["ssid"] ?? [];
            $config["ntp_server"] = $ntpServersString ?? null;
            $config["roaming"] = $data["roaming"] ?? null;
            $config["snmp"] = $data["snmp"] ?? null;
            $config["syslog-ng"] = $syslogProfileStatus == "active" ? 1 : 0;
            $config["nlbwmon"] = 0;
            $config["mwan3"] = $NetworkProfileStatus == "active" ? 1 : 0;


        } else {

            $firmwareFileName = "";
            $firmwareFilepath = "";
            $firmwareFileHash = "";

            //update new firmware file for model
            $routerModelId = $wifiRouter->model_id;
            if($routerModelId !== null || $routerModelId !== "") {
                $modelFirmwareVersion = Models::where("id", $routerModelId)->first();
                if($modelFirmwareVersion && $wifiRouter->firmwareVersion == $modelFirmwareVersion->firmware_version) {
                    $firmwareFile = ModelFirmwares::where("model_id", $wifiRouter->model_id)->where("released", 1)->first();
                    if ($firmwareFile) {
                        $firmwareFileName = $firmwareFile->file_name;
                        $firmwareFilepath = $url . "/firmware/updates/" . $firmwareFileName;
                        $firmwareFileHash = $firmwareFile->firmware_file;
                    } else {
                        // Handle case where firmware file is not found
                        $firmwareFileName = "";
                        $firmwareFilepath = "";
                        $firmwareFileHash = "";
                    }
                }
            }

            $domain = ltrim($domain, ',');

            $config["radios"] = [
                [
                    "band" => "2g",
                    "htmode" => "HT40",
                    "channel" => "auto",
                    "disabled" => false,
                    "txpower" => 'auto'
                ],
                [
                    "band" => "5g",
                    "htmode" => "HE80",//"HE20",
                    "channel" => "36",
                    "disabled" => false,
                    "txpower" => 'auto'
                ]
            ];

            if (isset($networkSettings->support_essid)) {
                $config["ssids"][] = $pmwaniSupportSsid;
            }

            if (isset($locationController->personal_essid)) {
                $config["ssids"][] = [
                    "network_type" => "private",
                    "essid" => $locationController->personal_essid,
                    "security" => "WPA2-Personal",
                    "hidden" => false,
                    "password" => $locationController->personal_essid_password,
                    "mode" => "ap",
                    "disabled" => false,
                    "maxassoc" => 100,
                    "radios" => ["2g", "5g"]
                ];
            }

            $whitelist  = array_unique($whitelistArray);
            $domainWhitelist = array_unique($domainWhitelistArray);

            /*if ($pmwaniDisabled !== true) {
                $config["ssids"][] = $pmwaniSsid;
            }*/
            $config["ssids"][] = $pmwaniSsid;

            /* $config['guestEssid'] = $networkSettings->essid;
             $config['supportEssid'] = $networkSettings->support_essid;
             $config['supportEssidHidden'] = $networkSettings->support_essid_hide;
             $config['supportEssidPassword'] = $networkSettings->support_essid_password;
             if($locationController){
                 $config['homeEssid'] = $locationController->personal_essid;
                 $config['homeEssidPassword'] = $locationController->personal_essid_password;
             }else{
                 $config['homeEssid'] = '';
                 $config['homeEssidPassword'] = '';
             }
             $config['radius_server'] = $networkSettings->radiusServer;
             $config['radius_secret'] = $networkSettings->radiusSecret;
             $config['login_url'] = $networkSettings->loginUrl;
             $config['whitelist'] = $whitelist;
             $config['domain'] = $domain;
             $config['domainWhitelist'] = $domainWhitelist;*/

        }

        $userDns = json_decode($networkSettings->user_dns, true);
        $deviceDns = json_decode($networkSettings->device_dns, true);
        $userDnsString = $userDns;
        // Concatenate deviceDns values into a comma-separated string
        if(isset($deviceDns)) {
            $deviceDnsString = implode(",", $deviceDns);
        }

        if (empty($userDnsString)) {

            // Set default values for $userDns
            $userDnsDefault = ["1.1.1.1", "8.8.8.8"];
            $userDnsString = $userDnsDefault;
        }
        if (empty($deviceDnsString)) {
            // Set default values for $deviceDns
            $deviceDnsDefault = ["1"];
            //$deviceDnsString = implode(",", $deviceDnsDefault);
        }

        $config["domain"] = $domain ?? "";
        $config["config_version"] = $wifiRouter->configurationVersion;
        $config["location_id"] = $wifiRouter->location_id;
        $config["dns1"] = "1.1.1.1";  //$userDnsString[0];
        $config["dns2"] = "8.8.8.8";  //$userDnsString[1];
        $config["macauth"] = "0";
        $config["status"] = "1";
        $config["update_file"] = !empty($firmwareFileName) ? $firmwareFileName : "";
        $config["update_file_hash"] = !empty($firmwareFileHash) ? $firmwareFileHash : "";
        $config["log_server_url"] = $networkSettings->log_server_url;
        $config["restart"] = $wifiRouter->reboot_required ?? 0;
        $config["reset"] = $wifiRouter->reset_required ?? 0;
        $config["factory_reset"] = $wifiRouter->reset_factory_required ?? 0;
        $config["latest_version"] = $wifiRouter->firmwareVersion;
        $config["device_dns"] = $deviceDnsString;
        $config["mqtt_host"] = env('MQTT_HOST');
        $config["mqtt_user"] = env('MQTT_USERNAME');
        $config["mqtt_pass"] = env('MQTT_PASSWORD');
        $config["mqtt_port"] = env('MQTT_PORT');
        $config["mqtt_group"] = env('MQTT_GROUP');

        if ($wifiRouter->reset_required == "1") {
            $wifiRouter->fill(["reset_required" => "0"])->save();
        }
        if ($wifiRouter->reboot_required == "1") {
            $wifiRouter->fill(["reboot_required" => "0"])->save();
        }

        return response()->json([
            "status" => true,
            "message" => "WiFi Router Config retrieved",
            "config" => $config
        ], 200);
    }

    public function config($key, $secret)
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";

        $networkSettings = NetworkSettings::where('id',1)->first();

        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();

        $domain = $networkSettings->loginUrl;
        $userDns = json_decode($networkSettings->user_dns, true);
        $deviceDns = json_decode($networkSettings->device_dns, true);
        $userDnsString = $userDns;
        // Concatenate deviceDns values into a comma-separated string
        if(isset($deviceDns))
        {
            $deviceDnsString = implode(',', $deviceDns);
        }

        //return $deviceDnsString;
        if (empty($userDnsString)) {
            // Set default values for $userDns
            $userDnsDefault = ['1.1.1.1', '8.8.8.8'];
            $userDnsString = $userDnsDefault;
        }
        // Check if $deviceDnsString is empty
        if (empty($deviceDnsString)) {
            // Set default values for $deviceDns
            $deviceDnsDefault = ['1'];
            $deviceDnsString = implode(',', $deviceDnsDefault);
        }

        $whitelist = env('WHITELIST', "staging.pmwani.net,testing.immunitynetworks.com,login.immunitynetworks.com,wifiadmin.immunitynetworks.com,portal2.bsnl.in,pgi.billdesk.com");
        $domainWhitelist = env('DOMAIN_WHITELIST',".immunitynetworks.com,.pmwani.net,.bsnl.in,.onlinesbi.sbi,.onlinesbi.com,.sbi.co.in,.sbicard.com,.akamaihd.net,.cloudflare.com,.cloudfront.net,.adobedtm.com,.billdesk.com,.googletagmanager.com,.google-analytics.com");

        $firmwareFileName = "";
        $firmwareFilepath = "";
        $firmwareFileHash = "";

        //update new firmware file for model
        $routerModelId = $wifiRouter->model_id;
        if($routerModelId !== null || $routerModelId !== ""){
            $modelFirmwareVersion = Models::where('id', $routerModelId)->first();
            if($modelFirmwareVersion && $wifiRouter->firmwareVersion == $modelFirmwareVersion->firmware_version) {
                $firmwareFile = ModelFirmwares::where('model_id', $wifiRouter->model_id)->where('released', 1)->first();
                if ($firmwareFile) {
                    $firmwareFileName = $firmwareFile->file_name;
                    $firmwareFilepath = $url . '/firmware/updates/' . $firmwareFileName;
                    $firmwareFileHash = $firmwareFile->firmware_file;
                } else {
                    // Handle case where firmware file is not found
                    $firmwareFileName = "";
                    $firmwareFilepath = "";
                    $firmwareFileHash = "";
                }
            }
        }

        if($wifiRouter){
            $locationController = Location::where('id',$wifiRouter->location_id)->first();

            //check if location exists
            if($locationController) {
                //if location has any Zone profile ID
                if ($locationController->profile_id !== NULL) {
                    $zoneProfile = BrandingProfile::where('id', $locationController->profile_id)->first();

                    //Get all URLs
                    $bannerRedirectUrl = isset($zoneProfile->banner_url) ? parse_url($zoneProfile->banner_url) : '';
                    $privacyPolicyUrl = isset($zoneProfile->privacy_policy) ? parse_url($zoneProfile->privacy_policy) : '';
                    $termsConditionsUrl = isset($zoneProfile->terms_conditions) ? parse_url($zoneProfile->terms_conditions) : '';
                    $whitelistedUrlJson = isset($zoneProfile->whitelisted_urls) ? json_decode($zoneProfile->whitelisted_urls, true) : '';

                    $urlsToAdd = [];

                    if ($bannerRedirectUrl && isset($bannerRedirectUrl['scheme']) && $bannerRedirectUrl['scheme'] === 'https' && isset($bannerRedirectUrl['host'])) {
                        $urlsToAdd[] = $bannerRedirectUrl['host'];
                    }
                    if ($privacyPolicyUrl && isset($privacyPolicyUrl['scheme']) && $privacyPolicyUrl['scheme'] === 'https' && isset($privacyPolicyUrl['host'])) {
                        $urlsToAdd[] = $privacyPolicyUrl['host'];
                    }
                    if ($termsConditionsUrl && isset($termsConditionsUrl['scheme']) && $termsConditionsUrl['scheme'] === 'https' && isset($termsConditionsUrl['host'])) {
                        $urlsToAdd[] = $termsConditionsUrl['host'];
                    }

                    /*foreach ($whitelistedUrlJson['urls'] as $whitelistedUrl) {
                        $parsedWhitelistedUrl = parse_url($whitelistedUrl);
                            if (isset($parsedWhitelistedUrl['path'])) {
                            $urlsToAdd[] = $parsedWhitelistedUrl['path'];
                    }*/
                    if ($whitelistedUrlJson && isset($whitelistedUrlJson['urls']) && is_array($whitelistedUrlJson['urls'])) {
                        foreach ($whitelistedUrlJson['urls'] as $whitelistedUrl) {
                            $parsedWhitelistedUrl = parse_url($whitelistedUrl);
                            if (isset($parsedWhitelistedUrl['host'])) {
                                $urlsToAdd[] = $parsedWhitelistedUrl['host'];
                            } elseif (isset($parsedWhitelistedUrl['path'])) {
                                $urlsToAdd[] = $parsedWhitelistedUrl['path'];
                            }
                        }
                    }

                    // Separate URLs into $whitelist and $domainWhitelist
                    foreach ($urlsToAdd as $urlToAdd) {
                        if (strpos($urlToAdd, '*') === 0) {
                            $domainWhitelist .= ',' . substr($urlToAdd, 1);
                        } else {
                            $whitelist .= ',' . $urlToAdd;
                            $domain .= ',' . $urlToAdd;
                        }
                    }

                    $pdoNetworkSetting = PdoNetworkSetting::where('pdo_id',$zoneProfile->pdo_id)->first();

                    if ($pdoNetworkSetting && $pdoNetworkSetting->essid !== null) {
                        $networkSettings->essid = $pdoNetworkSetting->essid;
                    }
                }
            }

            $whitelist = ltrim($whitelist, ',');
            $domain = ltrim($domain, ',');
            $domainWhitelist = ltrim($domainWhitelist, ',');

            /*
            $config = array();
            $config['configVersion'] = $wifiRouter->configurationVersion;
            $config['essid'] = $networkSettings->essid;
            $config['radiusServer'] = $networkSettings->radiusServer;
            $config['radiusSecret'] = $networkSettings->radiusSecret;
            $config['loginUrl'] = $networkSettings->loginUrl;
            $config['dns1'] = '1.1.1.1';
            $config['dns2'] = '8.8.8.8';
            */

            $mac = str_replace('-', '', $wifiRouter->macAddress);
            $mac_address_last_digits = substr($mac, -6);
            $networkSettings->support_essid .= '-' . $mac_address_last_digits;

            $config = array();
            $config['config_version'] = $wifiRouter->configurationVersion;
            $config['location_id'] = $wifiRouter->location_id;
            $config['guestEssid'] = $networkSettings->essid;
            $config['supportEssid'] = $networkSettings->support_essid;
            $config['supportEssidHidden'] = $networkSettings->support_essid_hide;
            $config['supportEssidPassword'] = $networkSettings->support_essid_password;
            if($locationController){
                $config['homeEssid'] = $locationController->personal_essid;
                $config['homeEssidPassword'] = $locationController->personal_essid_password;
            }else{
                $config['homeEssid'] = '';
                $config['homeEssidPassword'] = '';
            }
            $config['radius_server'] = $networkSettings->radiusServer;
            $config['radius_secret'] = $networkSettings->radiusSecret;
            $config['login_url'] = $networkSettings->loginUrl;
            $config['dns1'] =  $userDnsString[0];
            $config['dns2'] =  $userDnsString[1];
            $config['timezone'] = '1';
            $config['macauth'] = '0';
            $config['status'] = '1'; //$wifiRouter->status;
            $config['domain'] = $domain;
            //$config['whitelist'] = env('WHITELIST',"staging.pmwani.net,wifiadmin.immunitynetworks.com"); //$_ENV['WHITELIST'];
            //$config['domainWhitelist'] = env('DOMAIN_WHITELIST',".immunitynetworks.com,.pmwani.net"); //$_ENV['WHITELIST'];
            $config['whitelist'] = $whitelist;
            $config['domainWhitelist'] = $domainWhitelist;
            $config['update_file'] = !empty($firmwareFileName) ? $firmwareFileName : '';
            $config['update_file_hash'] = !empty($firmwareFileHash) ? $firmwareFileHash : '';
            /*$config['update_file'] = '';
            $config['update_file_hash'] = '';*/
            $config['log_server_url'] = $networkSettings->log_server_url;
            $config['download_limit'] = ceil(($wifiRouter->download_speed)*.90)*1000;
            $config['upload_limit'] = ceil(($wifiRouter->upload_speed)*.90)*1000;
            $config['restart'] = $wifiRouter->reboot_required;
            $config['reset'] = $wifiRouter->reset_required;
            $config['latest_version'] = $wifiRouter->firmwareVersion;

            $config['device_dns'] = $deviceDnsString;

            if($wifiRouter->reset_required == '1'){
                $wifiRouter->fill(['reset_required' => '0'])->save();
            }
            if($wifiRouter->reboot_required == '1') {
                $wifiRouter->fill(['reboot_required' => '0'])->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'WiFi Router Config retrived',
                'config' => $config
            ], 200);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'WiFi Router Config retrival failed',
            ], 401);
        }
    }

    public function config_vikram($key, $secret) {

        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();

        return response()->json([
            'status' => true,
            'message' => 'WiFi Router Config retrieved',
            'config' => [
                "radios" => [
                    [
                        "band" => "2g",
                        "htmode" => "HT20",
                        "channel" => "1",
                        "disabled" => false,
                        "txpower" => "auto"
                    ],
                    [
                        "band" => "5g",
                        "htmode" => "HE80",
                        "channel" => "36",
                        "disabled" => false,
                        "txpower" => "auto"
                    ]
                ],
                "ssids" => [
                    [
                        "network_type" => "support",
                        "essid" => "immunity_support",
                        "security" => "WPA2-Personal",
                        "hidden" => false,
                        "password" => "123456789",
                        "mode" => "ap",
                        "disabled" => false,
                        "maxassoc" => 100,
                        "radios" => ["2g", "5g"]
                    ],
                    [
                        "network_type" => "private",
                        "essid" => "immunity_private",
                        "security" => "WPA2-Personal",
                        "hidden" => false,
                        "password" => "123456789",
                        "mode" => "ap",
                        "disabled" => false,
                        "maxassoc" => 100,
                        "radios" => ["2g", "5g"]
                    ],
                    [
                        "network_type" => "hotspot-pw",
                        "essid" => "immunity_hotspot_pw",
                        "hidden" => false,
                        "mode" => "ap",
                        "radios" => ["2g", "5g"],
                        "radius_ip" => "207.180.210.98",
                        "radius_secret" => "sxBRyXcVKvLfJcTnGE62ztdU",
                        "login_url" => "http://login.nevrio.tech",
                        "whitelist" => ["login.nevrio.tech","pdo.pmwani.net", "wifiadmin.immunitynetworks.com","portal2.bsnl.in","pgi.billdesk.com"],
                        "whitelist_domains" => [".immunitynetworks.com", ".abcd.net", ".razorpay.com", ".googleapis.com", ".icicibank.com", ".kotak.com", ".nevrio.tech",".bsnl.in",".onlinesbi.sbi",".onlinesbi.com",".sbi.co.in",".sbicard.com",".akamaihd.net",".cloudflare.com",".cloudfront.net",".adobedtm.com",".billdesk.com",".googletagmanager.com",".google-analytics.com"],
                        "disabled" => false,
                        "nasid" => "156",
                        "security" => "none"
                    ],
                    [
                        "network_type" => "hotspot-cp",
                        "essid" => "immunity_hotspot_cp",
                        "hidden" => false,
                        "radios" => ["2g", "5g"],
                        "mode" => "ap",
                        "radius_ip" => "207.180.210.98",
                        "radius_secret" => "sxBRyXcVKvLfJcTnGE62ztdU",
                        "login_url" => "http://login.nevrio.tech",
                        "whitelist" => ["login.nevrio.tech","pdo.pmwani.net", "wifiadmin.immunitynetworks.com","portal2.bsnl.in","pgi.billdesk.com"],
                        "whitelist_domains" => [".immunitynetworks.com", ".abcd.net", ".razorpay.com", ".googleapis.com", ".icicibank.com", ".kotak.com", ".nevrio.tech",".bsnl.in",".onlinesbi.sbi",".onlinesbi.com",".sbi.co.in",".sbicard.com",".akamaihd.net",".cloudflare.com",".cloudfront.net",".adobedtm.com",".billdesk.com",".googletagmanager.com",".google-analytics.com"],
                        "disabled" => false,
                        "nasid" => "156",
                        "security" => "none"
                    ]
                ],
                "config_version" => $wifiRouter->configurationVersion ?? '1',
                "location_id" => 156,
                "dns1" => "1.1.1.1",
                "dns2" => "8.8.8.8",
                "timezone" => "1",
                "macauth" => "0",
                "status" => "1",
                "domain" => "login.nevrio.tech",
                "update_file" => "",
                "update_file_hash" => "",
                "log_server_url" => "pdo.nevrio.tech",
                "download_limit" => 90000,
                "upload_limit" => 90000
            ]
        ], 200);



    }

    /*
    public function config($key, $secret)
    {
        $wifiRouter = Router::where('key', $key)->where('secret', $secret)->get();
        if ($wifiRouter) {
            $networkSettings = NetworkSettings::where('id', 1)->get();

            $config = array();
            $config['configVersion'] = $wifiRouter->configurationVersion;
            $config['essid'] = $networkSettings->essid;
            $config['radiusServer'] = $networkSettings->radiusServer;
            $config['radiusSecret'] = $networkSettings->radiusSecret;
            $config['loginUrl'] = $networkSettings->loginUrl;

            if($wifiRouter->reset_required == '1'){
                $wifiRouter->fill(['reset_required' => '0'])->save();
            }
            if($wifiRouter->reboot_required == '1') {
                $wifiRouter->fill(['reboot_required' => '0'])->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'WiFi Router Config retrived',
                'config' => $config
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'WiFi Router Config retrival failed',
            ], 401);
        }
    }
    */

    /*
    public function verify($mac)
    {
        $wifiRouter = Router::where('macAddress',$mac)->get();
        if($wifiRouter){
            return response()->json([
                'status' => true,
                'message' => 'WiFi Router Details retrived',
                'wifiRouter' => $wifiRouter
            ], 201);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'WiFi Router Does not exists',
            ], 401);
        }
    }
    */

    public function verify($verification_code, $mac, $config_type)
    {
        if ($verification_code == '7bjxMEfmwfsDSjqKxpwC') {
            $mac = strtoupper($mac);
            $wiFiRouter = Router::select('id','location_id','secret', 'key')->where('mac_address',$mac)->first();

            if ($wiFiRouter) {
                switch ($config_type) {
                    case "key":
                        echo $wiFiRouter->key . $mac;
                        break;
                    case "secret":
                        echo $wiFiRouter->secret . $mac;
                        break;
                }
            } else {
                echo 'XXXXXXXX';
            }
        } else {
            echo 'XXXXXXXX';
        }
    }

    public function verify_router($verification_code, $mac)
    {
        if($verification_code == env('VERIFICATION_CODE',"FrUu2ZdxCJ8Xpm8acG8q")){ //$_ENV['VERIFICATION_CODE']

            $wiFiRouter = Router::select('id','location_id','secret', 'key')->where('mac_address',$mac)->first();

            if($wiFiRouter){
                $wiFiRouter->installer = 'YhH2PYGA3n62FJBCKTnV8Vfw-v4.sh';
                //$wiFiRouter->installer = 'YhH2PYGA3n62FJBCKTnV8Vfw-v5-wip.sh';
                return response()->json([
                    'success' => true,
                    'message' => 'WiFi Router verified successfully',
                    'data' => $wiFiRouter
                ], 200);
            } else{
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization failed',
                ], 400);
            }
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Authorization failed',
            ], 400);
        }
    }

    public function heartbeat($key, $secret, Request $request)
    {
        //Log::info("Heartbeat Request:- " . $request);
        //Log::info($request->root());

        $validator = Validator::make($request->all(), [
            'cpu_usage' => 'string|nullable',
            'disk_usage' => 'string|nullable',
            'ram_usage' => 'string|nullable',
            'latest_version' => 'string|nullable',
            'network_speed' => 'string|nullable',
            'tar_version' => 'string|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $wifiRouter = Router::where('key', $key)->where('secret', $secret)->first();

        if ($wifiRouter) {
            $wifiRouter->fill(['lastOnline' => now()]);
            if ($request->has('tar_version')) {
                $tarVersion = $request->input('tar_version');

                // Update latest_tar_version only if the value is different
                if ($wifiRouter->latest_tar_version !== $tarVersion) {
                    $wifiRouter->latest_tar_version = $tarVersion;
                }
            }
            $wifiRouter->save();

            $wifi_router_id = $wifiRouter->id;

            $input = $request->all();
            $input['wifi_router_id'] = $wifi_router_id;

            // Check if the slow_network key exists and if the column exists in the database
            if ($request->has('network_speed') && Schema::hasColumn('wi_fi_statuses', 'network_speed')) {
                $input['network_speed'] = $request->input('network_speed');
            }

            // check the contion for if mqtt heartbeat is present or not
            $mqttLive = MqttApsLiveStatus::where('mac', $wifiRouter->mac_address)
                ->where('updated_at', '>=', now()->subMinute())
                ->first();  
            if (!$mqttLive) { 
                $add_status = WiFiStatus::create($input);
            }
            // if($wifiRouter->macAddress == '50-48-2C-30-09-1F' || $wifiRouter->macAddress == '50-48-2C-30-07-33') {
            //     $wifiRouter->custom_file = "update_mqtt.sh";
            //     $wifiRouter->custom_file_path = "/etc/update_mqtt.sh";
            //     $wifiRouter->custom_command = "sleep 10 && sh /etc/update_mqtt.sh";
            // }
            
            return response()->json([
                'success' => true,
                'message' => 'WiFi Router Heartbeat recorded',
                'data' => $wifiRouter,
                'request' => $input,
                'log_status' => '1'
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'WiFi Router Heartbeat was not recorded',
            ], 401);
        }
    }

    public function wifilogin(Request $request)
    {
        $username = 'FreeWiFi-user';
        $password = 'FreeWiFi-user';
        $challenge = $request->challenge;
        $uamsecret = '';

        $hexchal = pack("H32", $challenge);
        $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
        $response = md5("\0" . $password . $newchal);
        $newpwd = pack("a32", $password);
        $pappassword = implode('', unpack("H32", ($newpwd ^ $newchal)));

        $data = array();

        $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username=' . $username . '&response=' . $response . '&userurl=';
        $data['response'] = $response;

        return response()->json([
            'success' => true,
            'message' => "WiFi Login Successful",
            "data" => $data,
            "request" => $request
        ], 200);
    }

    public function send_otp(Request $request)
    {

    }

    public function verify_otp(Request $request)
    {
        $username = 'FreeWiFi-user';
        $password = 'FreeWiFi-user';
        $challenge = $request->challenge;
        $uamsecret = '';

        $hexchal = pack("H32", $challenge);
        $newchal = $uamsecret ? pack("H*", md5($hexchal . $uamsecret)) : $hexchal;
        $response = md5("\0" . $password . $newchal);
        $newpwd = pack("a32", $password);
        $pappassword = implode('', unpack("H32", ($newpwd ^ $newchal)));

        $data = array();

        $data['login_redirect_url'] = 'http://172.22.100.1:3990/logon?username=' . $username . '&response=' . $response . '&userurl=';
        $data['response'] = $response;

        return response()->json([
            'success' => true,
            'message' => "WiFi Login Successful",
            "data" => $data,
            "request" => $request
        ], 200);
    }

    public function update(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:wi_f_routers,id|',
            'modelNumber' => 'max:100',
            'serialNumber' => 'max:50',
            'name' => 'string'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        $wifiRouter = Router::findOrFail($validator->validated()['id']);
        $input = $request->all();

        $wifiRouter->fill($validator->validated())->save();
        return response()->json([
            'status' => true,
            'message' => 'WiFi Router updated',
            'wifiRouter' => $wifiRouter
        ], 201);
    }

    public function wifi_monitoring(Request $request)
    {
        /*
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:wi_f_routers,id|',
            'modelNumber' => 'max:100',
            'serialNumber' => 'max:50',
            'name' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }*/
    }

    public function ip_logging2($router_key, Request $request)
    {
        $userIpAddr = $this->getUserIpAddr();
        event(new UserIPAccessLogEvent($request[0], $router_key, $userIpAddr));
    }
    public function ip_logging_kernel($router_key, Request $request)
    {
        //Log::info("ip_logging_kernel=" );
        //Log::info($router_key); 
        //Log::info("ip_logging_kernel11=");
        //Log::info($request->all());
        
        $router = Router::where('key',$router_key)->first();
        $router_mac = $router->mac_address;
        $location_id = $router->location_id;
        $router_id = $router->id;
        if(isset($request[0]['PRIORITY']) && isset($request[0]['PID']) && $request[0]['PID'] != "NEW" && $request[0]['PID'] != "DESTROY"){
            $kernelLog = new KernelLog;
            $kernelLog->router_id = $router_id;
            $kernelLog->program = (isset($request[0]['PRIORITY']))?$request[0]['PRIORITY']:'';
            $kernelLog->priority = (isset($request[0]['PRIORITY']))?$request[0]['PRIORITY']:'';
            $kernelLog->pid = (isset($request[0]['PID']))?$request[0]['PID']:'';
            $kernelLog->msg = (isset($request[0]["MESSAGE"]))?$request[0]["MESSAGE"]:'';
            $kernelLog->host = (isset($request[0]["HOST"]))?$request[0]["HOST"]:'';
            $kernelLog->facility = (isset($request[0]["FACILITY"]))?$request[0]["FACILITY"]:'';
            $kernelLog->source = (isset($request[0]["SOURCE"]))?$request[0]["SOURCE"]:'';
            $kernelLog->date = (isset($request[0]["DATE"]))?$request[0]["DATE"]:'';
            $kernelLog->save();
        }
    }

    public function ip_logging($router_key, Request $request)
    {
        /*
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:wi_f_routers,id|',
            'modelNumber' => 'max:100',
            'serialNumber' => 'max:50',
            'name' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }
        */

        $router = Router::where('key',$router_key)->first();
        $router_mac = $router->mac_address;
        $location_id = $router->location_id;
        $router_id = $router->id;

        try{
            if(isset($request[0]['LEGACY_MSGHDR']) && (($request[0]['LEGACY_MSGHDR'] != '[NEW]') && ($request[0]['LEGACY_MSGHDR'] != '[DESTROY]')))
            {
                $msgLog = new MessageLog;
                $msgLog->router_id = $router->id;
                $msgLog->program = (isset($request[0]['PRIORITY']))?$request[0]['PRIORITY']:'';
                $msgLog->priority = (isset($request[0]['PRIORITY']))?$request[0]['PRIORITY']:'';
                $msgLog->pid = (isset($request[0]['PID']))?$request[0]['PID']:'';
                $msgLog->msg = (isset($request[0]["MESSAGE"]))?$request[0]["MESSAGE"]:'';
                $msgLog->host = (isset($request[0]["HOST"]))?$request[0]["HOST"]:'';
                $msgLog->facility = (isset($request[0]["FACILITY"]))?$request[0]["FACILITY"]:'';
                $msgLog->save();
            }
            if(isset($request[0]['LEGACY_MSGHDR']) && (($request[0]['LEGACY_MSGHDR'] == '[NEW]') || ($request[0]['LEGACY_MSGHDR'] == '[DESTROY]'))){
                $data = explode (' ', $request[0]['MESSAGE']);
                $new_log = array();
                $new_log['src_ip'] = substr($data[1],4);
                if(isset($request[0]['PRIORITY']) && $request[0]['PRIORITY'] == 'err')
                {
                    $msgLog = new MessageLog;
                    $msgLog->router_id = $router->id;
                    $msgLog->program = (isset($request[0]['PRIORITY']))?$request[0]['PRIORITY']:'';
                    $msgLog->priority = (isset($request[0]['PRIORITY']))?$request[0]['PRIORITY']:'';
                    $msgLog->pid = (isset($request[0]['PID']))?$request[0]['PID']:'';
                    $msgLog->msg = (isset($request[0]["MESSAGE"]))?$request[0]["MESSAGE"]:'';
                    $msgLog->host = (isset($request[0]["HOST"]))?$request[0]["HOST"]:'';
                    $msgLog->facility = (isset($request[0]["FACILITY"]))?$request[0]["FACILITY"]:'';
                    $msgLog->save();
                }
                if( substr($new_log['src_ip'],0,10) == '172.22.100'){
                    $user_info = DB::connection('mysql2')
                        ->table('radacct')
                        ->where('framedipaddress', $new_log['src_ip'])
                        ->where('location_id', $location_id)
                        ->orderBy('radacctid','desc')
                        ->first();
                    // Log::info($user_info->all());

                    $new_log['dest_ip'] = substr($data[2],4);
                    $new_log['username'] = $user_info && $user_info->username ? substr($user_info->username,0,10) : "N/A";
                    $new_log['port'] = substr($data[5],4);
                    $new_log['protocol'] = substr($data[3],6);
                    $new_log['client_device_translated_ip'] = $this->getUserIpAddr();
                    $new_log['client_device_ip'] = $user_info && $user_info->nasipaddress ? $user_info->nasipaddress : substr($data[1],4);
//                    $new_log['username'] = $user_info->username;
                    $new_log['src_port'] = substr($data[4],4);
                    $new_log['dest_port'] = substr($data[5],4);
                    $new_log['client_device_ip_type'] = 'dynamic';
                    $new_log['location_id'] = $location_id;
                    $new_log['router_id'] = $router_id;
                    $new_log['created_at'] = Carbon::now();
                    //$new_log['user_mac_address'] = $user_info->callingstationid ? $user_info->callingstationid : "N/A";
                    $new_log['user_mac_address'] = $user_info && $user_info->callingstationid ? $user_info->callingstationid : "N/A";

                    if($request[0]['LEGACY_MSGHDR'] == '[NEW]' && substr($new_log['src_ip'],0,10) == '172.22.100'){
                        /*                        $add_new_log = UserIPAccessLog::create($new_log);*/
                        DB::connection('mysql3')->table('user_i_p_access_logs')->insert($new_log);
                        /*                        $curl = curl_init();

                        curl_setopt_array($curl, array(
                            CURLOPT_URL => 'logs.pmwani.net:8011/handler.php?type=new',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => '',
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 0,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_FAILONERROR => true,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => 'POST',
                            CURLOPT_POSTFIELDS => array('src_ip' => $new_log['src_ip'],'dest_ip' => $new_log['dest_ip'],'protocol' => $new_log['protocol'],'port' => $new_log['port'],'username' => $new_log['username'],'src_port' => $new_log['src_port'],'dest_port' => $new_log['dest_port'],'client_device_ip' => $new_log['client_device_ip'],'client_device_ip_type' => $new_log['client_device_ip_type'],'client_device_translated_ip' => $new_log['client_device_translated_ip']),
                            CURLOPT_HTTPHEADER => array(
                                'Accept: application/json'
                            ),
                        ));

                                                $response = curl_exec($curl);
                                                if (curl_errno($curl)) {
                                                    $error_msg = curl_error($curl);
                                                }

                                                if (isset($error_msg)) {
                        //                            Log::error($error_msg);
                                                    Log::error('ADDING details to local DB');

                                                    $add_new_log = UserIPAccessLog::create($new_log);
                                                }else{
                                                    Log::error('Pushed/Added to Log server');
                                                }
                                                curl_close($curl);*/
                    }

                    if($request[0]['LEGACY_MSGHDR'] == '[DESTROY]'){
                        $old_record_new_log = DB::connection('mysql3')->table('user_i_p_access_logs')->where('src_ip',$new_log['src_ip'])->where('dest_ip',$new_log['dest_ip'])->first();
                        if($old_record_new_log){
                            /*$old_record_new_log->updated_at = Carbon::now();
                            $old_record_new_log->save();*/
                            DB::connection('mysql3')
                                ->table('user_i_p_access_logs')
                                ->where('src_ip', $new_log['src_ip'])
                                ->where('dest_ip', $new_log['dest_ip'])
                                ->update(['updated_at' => Carbon::now()]);
                        }
                        /*                        $curl = curl_init();

                                                curl_setopt_array($curl, array(
                                                    CURLOPT_URL => 'logs.pmwani.net:8011/handler.php?type=destroy',
                                                    CURLOPT_RETURNTRANSFER => true,
                                                    CURLOPT_ENCODING => '',
                                                    CURLOPT_MAXREDIRS => 10,
                                                    CURLOPT_TIMEOUT => 0,
                                                    CURLOPT_FOLLOWLOCATION => true,
                                                    CURLOPT_FAILONERROR => true,
                                                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                                    CURLOPT_CUSTOMREQUEST => 'POST',
                                                    CURLOPT_POSTFIELDS => array('src_ip' => $new_log['src_ip'],'dest_ip' => $new_log['dest_ip'],'protocol' => $new_log['protocol'],'port' => $new_log['port'],'username' => $new_log['username'],'src_port' => $new_log['src_port'],'dest_port' => $new_log['dest_port'],'client_device_ip' => $new_log['client_device_ip'],'client_device_ip_type' => $new_log['client_device_ip_type'],'client_device_translated_ip' => $new_log['client_device_translated_ip']),
                                                    CURLOPT_HTTPHEADER => array(
                                                        'Accept: application/json'
                                                    ),
                                                ));

                                                $response = curl_exec($curl);
                                                if (curl_errno($curl)) {
                                                    $error_msg = curl_error($curl);
                                                }

                                                if (isset($error_msg)) {
                                                    $old_record_new_log = UserIPAccessLog::where('src_ip',$new_log['src_ip'])->where('dest_ip',$new_log['dest_ip'])->first();
                                                    if($old_record_new_log){
                                                        $old_record_new_log->updated_at = Carbon::now();
                                                        $old_record_new_log->save();
                                                    }
                                                }else{
                                                    Log::info('Pushed/Updated to Log server');
                                                }
                                                curl_close($curl);*/
                    }
                }
            }
        } catch (Exception $e) {
            return ;
            # echo 'and the error is: ',  $e->getMessage(), "\n";
        }
    }

    public function getUserIpAddr(){
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
    
    public function custom_result(Request $request){
        $mac = $request->mac;
        $macFormatted = str_replace(':', '-', $mac);
        MqttApsResponse::create([
            'mac' => $macFormatted,
            'success' => $request->status ? true : false,
            'from_response' => $request->from ? $request->from : null,
            'json_response' => json_encode(['custom_file' => $request->custom_file, 'custom_file_path' => $request->custom_file_path]),
        ]);
        return response()->json([
            'success' => true
        ], 200);
    }

    public function latest_version($verification_code, $mac)
    {
        if ($verification_code == '7bjxMEfmwfsDSjqKxpwC') {
            $mac = strtoupper($mac);
            
            $wiFiRouter = Router::select('id','location_id','secret', 'key')->where('mac_address',$mac)->first();

            if($wiFiRouter){
                return response()->json([
                    'success' => true,
                    'message' => 'WiFi Router verified successfully',
                    'data' => $wiFiRouter,
                    'tar_version' => 9
                ], 200);
            } else{
                return response()->json([
                    'success' => false,
                    'message' => 'Authorization failed',
                ], 400);
            }
           
        } else {
            echo '';
        }
    }

    public function reset($key, $secret)
    {
        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();
        $config["reset"] = $wifiRouter->reset_required;
        return response()->json([
            "status" => true,
            "message" => "WiFi Router Config retrieved",
            "config" => $config
        ], 200);
    }

    public function reboot($key, $secret)
    {
        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();
        $config["reboot"] = $wifiRouter->reboot_required;
        return response()->json([
            "status" => true,
            "message" => "WiFi Router Config retrieved",
            "config" => $config
        ], 200);
    }

    public function ssid_configuration($key, $secret)
    {
        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();
        if($wifiRouter){
            $networkSettings = NetworkSettings::where('id',1)->first();
            $locationController = Location::where('id',$wifiRouter->location_id)->first();
            //id PDO has settup the default ESSID name
            $pdoEssid = "";
            $config["ssids"] = [];
            // dd($locationController);
            $pmwaniDisabled = false;
            if (isset($locationController) && $locationController->profile_id !== NULL) {
                $zoneProfile = BrandingProfile::where('id', $locationController->profile_id)->first();
                $pdoNetworkSetting = PdoNetworkSetting::where('pdo_id', $zoneProfile->pdo_id)->first();
                if ($pdoNetworkSetting && $pdoNetworkSetting->essid !== null) {
                    $pdoEssid = $pdoNetworkSetting->essid;
                }
                if ($zoneProfile && $zoneProfile->default_plans == 0) {
                    $pmwaniDisabled = true;
                }
            }
    
            //Pmwani default SSID & Immunity Support SSID
            $pmwaniSsid = [
                "network_type" => "hotspot-pw",
                "essid" => $pdoEssid !== "" ? $pdoEssid : $networkSettings->essid,
                "security" => "none",
                "hidden" => false,
                "mode" => "ap",
                "disabled" => false,
                "radios" => ["2g", "5g"],
                "radius_ip" => $networkSettings->radiusServer ?? "",
                "radius_secret" => $networkSettings->radiusSecret ?? "",
                "whitelist" => $whitelist ?? "",
                "whitelist_domains" => $domainWhitelist ?? "",
                "login_url" => "https://" . $networkSettings->loginUrl,
                "nasid" => $wifiRouter->location_id
            ];
    
            $pmwaniSupportSsid = [];
            if (isset($networkSettings->support_essid)) {
                $pmwaniSupportSsid = [
                    "network_type" => "support",
                    "essid" => $networkSettings->support_essid,
                    "security" => "WPA2-Personal",
                    "hidden" => !($networkSettings->support_essid_hide == 0),
                    "password" => $networkSettings->support_essid_password,
                    "mode" => "ap",
                    "disabled" => false,
                    "maxassoc" => 100,
                    "radios" => ["2g", "5g"]
                ];
            }
    
            $data["ssid"][] = $pmwaniSsid;
            $data["ssid"][] = $pmwaniSupportSsid;
    
            $config["ssids"] = $data["ssid"];
    
            return response()->json([
                "status" => true,
                "message" => "WiFi Router Config retrieved",
                "config" => $config
            ], 200);
        } else{
            // If the configuration retrieval failed, return a failed response
            return response()->json([
                "status" => false,
                "message" => "WiFi Router Config retrieval failed",
                "config" => null
            ], 400);
        }

    }
    

}
