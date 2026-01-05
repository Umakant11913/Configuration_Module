<?php

namespace App\Http\Controllers;

use App\Models\BrandingProfile;
use App\Models\Location;
use App\Models\ModelFirmwares;
use App\Models\Models;
use App\Models\NetworkSettings;
use App\Models\PdoNetworkSetting;
use App\Models\Router;
use App\Models\WifiConfigurationProfiles;
use DB;
class ConfigurationController extends Controller

{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['config']]);
    }

    public function config($key, $secret)
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";

        $wifiRouter = Router::where('key',$key)->where('secret',$secret)->first();

        $networkSettings = NetworkSettings::where('id',1)->first();
        $locationController = Location::where('id',$wifiRouter->location_id)->first();

        $domain = $networkSettings->loginUrl;

        $defaultWhitelist = "staging.pmwani.net,pdo.pmwani.net,wifiadmin.immunitynetworks.com,demo.immunitynetworks.com,demo-login.immunitynetworks.com,demo-api.immunitynetworks.com,portal2.bsnl.in,pgi.billdesk.com";
        $defaultWhitelist = "";
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

        if ($locationController->profile_id !== NULL) {
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
        //new code
        if($wifiRouter) {
            $wifiLocation = Location::where('id', $wifiRouter->location_id)->first();
            if($wifiLocation) {
                $wifiLocationConfigurationId = $wifiLocation->wifi_configuration_profile_id;
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

        //id PDO has settup the default ESSID name
        $pdoEssid = "";

        if ($locationController->profile_id !== NULL) {
            $zoneProfile = BrandingProfile::where('id', $locationController->profile_id)->first();
            $pdoNetworkSetting = PdoNetworkSetting::where('pdo_id', $zoneProfile->pdo_id)->first();
            if ($pdoNetworkSetting && $pdoNetworkSetting->essid !== null) {
                $pdoEssid = $pdoNetworkSetting->essid;
            }
        }

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
                "essid" => "Immunity-Support", //$networkSettings->support_essid,
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
            $data = json_decode($confugrationProfile->settings, true);

            //for radios
            $radio_2g_data = $data["radio_2g"];
            $radio_5g_data = $data["radio_5g"];
            $radiosArray = [
                [
                    "band" => "2g",
                    "htmode" => "HT40",
                    "channel" => implode(',', $radio_2g_data["channel"]),
                    "disabled" => !$radio_2g_data["enabled"],
                    "txpower" => $radio_2g_data["tx_power"]
                ],
                [
                    "band" => "5g",
                    "htmode" => "HE20",
                    "channel" => implode(',', $radio_5g_data["channel"]),
                    "disabled" => !$radio_5g_data["enabled"],
                    "txpower" => $radio_5g_data["tx_power_5g"]
                ]
            ];

            $radiosEnabled = [];
            foreach ($data["ssid"] as $ssid) {
                if ($ssid["radio_2g"] || $ssid["radio_5g"]) {
                    $radiosEnabled[] = "2g";
                    $radiosEnabled[] = "5g";
                    break; // Break the loop once any SSID with enabled radios is found
                }
            }

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
            }

            $data["ssid"][] = $pmwaniSsid;
            $data["ssid"][] = $pmwaniSupportSsid;

            $ntp_servers = [];
            if (!empty($data["ntp_server_1"])) {
                $ntp_servers[] = $data["ntp_server_1"];
            }
            if (!empty($data["ntp_server_2"])) {
                $ntp_servers[] = $data["ntp_server_2"];
            }

            if(count($ntp_servers) > 0) {
                $ntpServersString = implode(",", $ntp_servers);
            }

            $config["radios"] = $radiosArray ?? [];
            $config["ssids"] = $data["ssid"] ?? [];
            $config["ntp_server"] = $ntpServersString ?? null;
            $config["roaming"] = $data["roaming"] ?? null;
            $config["snmp"] = $data["snmp"] ?? null;

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
                    "txpower" => 50
                ],
                [
                    "band" => "5g",
                    "htmode" => "HE20",
                    "channel" => "36",
                    "disabled" => false,
                    "txpower" => 100
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
        $config["timezone"] = "1";
        $config["macauth"] = "0";
        $config["status"] = "1";
        $config["update_file"] = !empty($firmwareFileName) ? $firmwareFileName : "";
        $config["update_file_hash"] = !empty($firmwareFileHash) ? $firmwareFileHash : "";
        $config["log_server_url"] = $networkSettings->log_server_url;
        $config["download_limit"] = ceil(($wifiRouter->download_speed) * .90) * 1000;
        $config["upload_limit"] = ceil(($wifiRouter->upload_speed) * .90) * 1000;
        $config["restart"] = $wifiRouter->reboot_required;
        $config["reset"] = $wifiRouter->reset_required;
        $config["latest_version"] = $wifiRouter->firmwareVersion;
        $config["device_dns"] = $deviceDnsString;

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

}

