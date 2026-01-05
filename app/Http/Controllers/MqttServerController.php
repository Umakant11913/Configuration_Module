<?php

namespace App\Http\Controllers;

use App\Models\MqttApsLiveStatus;
use App\Models\MqttApsResponse;
use App\Models\Router;
use App\Models\WiFiStatus;
use App\Services\ApMqttService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Jobs\ConfigureApViaMqtt;
use Psy\CodeCleaner\ReturnTypePass;

class MqttServerController extends Controller
{
    protected $mqtt;
    protected $port;
    protected $group;

    public function __construct(ApMqttService $mqtt)
    {
        $this->mqtt = $mqtt;
        $this->port = env('MQTT_PORT');
        $this->group = env('MQTT_GROUP');
    }

    public function store_heartbeat(Request $request)
    {
        // Log::info('Received MQTT from EMQX', $request->all());
        
        // Get the raw payload string from the request
        $payloadRaw = $request->input('payload');

        // Try decoding the JSON string
        $payload = json_decode($payloadRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to decode payload JSON', ['payload' => $payloadRaw]);
            return response()->json(['error' => 'Invalid payload format'], 400);
        }

        // Validate MAC address format (e.g., 6 pairs of hex digits separated by - or :)
        $mac = $payload['mac'] ?? null;
        if (!$mac || !preg_match('/^([0-9A-Fa-f]{2}([-:])){5}([0-9A-Fa-f]{2})$/', $mac)) {
            Log::error('Invalid or missing MAC address in heartbeat', ['mac' => $mac]);
            return response()->json(['error' => 'Invalid or missing MAC address'], 400);
        }        

        MqttApsLiveStatus::updateOrCreate(
            ['mac' => $mac], 
            [ 
                'status' => $payload['status'] ?? null,
                'json_data' => json_encode($payload)
            ]
        );
        // this code are to update route of lastupdate column
        $wifiRouter = Router::where('mac_address', $mac)->first();
        if($wifiRouter){
            $wifiRouter->fill(['lastOnline' => now()]);
            $wifiRouter->save();
        }
       
        // $input = $request->all();
        $input['cpu_usage'] = $payload['cpu_usage'];
        $input['disk_usage'] = $payload['disk_usage'];
        $input['ram_usage'] = $payload['ram_usage'];
        $input['latest_version'] = $payload['latest_version'];
        $input['network_speed'] = $payload['network_speed'];
        $input['tar_version'] = $payload['tar_version'];
        $input['wifi_router_id'] = $wifiRouter->id;
        $input['client_2g'] = array_key_exists('2.4_GHz_Clients', $payload) ? (int) $payload['2.4_GHz_Clients'] : 0;
        $input['client_5g'] = array_key_exists('5_GHz_Clients', $payload) ? (int) $payload['5_GHz_Clients'] : 0;
        if (!empty($payload['clients']) && is_array($payload['clients'])) {
            $input['client_details'] = $payload['clients'];
        }
        // Check if the slow_network key exists and if the column exists in the database
        // if ($request->has('network_speed') && Schema::hasColumn('wi_fi_statuses', 'network_speed')) {
        //     $input['network_speed'] = $request->input('network_speed');
        // }
        // Check if a WiFiStatus entry exists for this router in the last 1 minute
        $recentStatus = WiFiStatus::where('wifi_router_id', $wifiRouter->id)
            ->where('created_at', '>=', now()->subMinute())
            ->first();

        if (!$recentStatus) {
            WiFiStatus::create($input);
        }
        
        return response()->json(['status' => 'success', 'received' => $payload], 200);
    }

    public function clients_connected(Request $request)
    {
        // Optional: log the full payload
        // Log::info('EMQX Webhook Received Clients:', $request->all());

        return response()->json(['status' => 'ok'], 200);
    }
    
    public function updateApConfig(Request $request)
    {
        $devices = $request->input('devices');
               
        if (empty($devices) || !is_array($devices)) {
            return response()->json(['error' => 'Invalid or empty devices array'], 400);
        }

        foreach ($devices as $device) {
            if (!isset($device['mac'])) {
                Log::warning('Skipping device with missing MAC', ['device' => $device]);
                continue;
            }
            Log::info("device data", ['device'=> $device]);
            dispatch(new ConfigureApViaMqtt($device));                
        }

        // $this->mqtt->disconnect();
        return response()->json([
            'status' => 200,
            'message' => 'Configuration jobs dispatched to queue.',
        ]);
    }

    public function updateApConfig_single_response(Request $request)
    {
        if (empty($request->mac)) {
            return response()->json(['error' => 'MAC address is required'], 400);
        }
        $mac = $request->mac;
        $group = $this->group;
        // $topic = "ap/$mac/settings";
        // $responseTopic = "ap/$mac/response";
        $topic = "ap/$group/$mac/settings";
        $responseTopic = "ap/$group/$mac/response";

        $payload = json_encode($request->all());

        try {          
        
            $this->mqtt->connect();
            Log::info("Connected to broker");

            $pingTopic = "ap/{$group}/{$mac}/ping";
            $pongTopic = "ap/{$group}/{$mac}/pong";
            
            $pongResponse = $this->mqtt->pingDevice($mac, 10, $pingTopic, $pongTopic);
            if (!$pongResponse) {
                Log::warning("Device not online or did not respond to ping", ['mac' => $mac]);
                $this->mqtt->disconnect();
                MqttApsResponse::create([
                    'mac' => $mac,
                    'success' => false,
                    'from_response' => "mqtt",
                    'json_response' => json_encode(['message' => 'Device not online or did not respond']),
                ]);
                // return response()->json([
                //     'status' => 'timeout',
                //     'reply'  => 'Device not online',
                // ]);
                return response()->json(['message' => 'Device not online or did not respond']);
            }
            // Log::info("Device is online: " . $mac);
            // Publish config
            $this->mqtt->publish($topic, $payload);

            // Wait for reply from AP
            $reply = $this->mqtt->waitForResponseSingal($responseTopic, 5);

            $this->mqtt->disconnect();

            // return response()->json([
            //     'status' => $reply ? 'success' : 'timeout',
            //     'reply'  => $reply,
            // ]);
            return response()->json($reply);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'MQTT connect failed',
                'error' => $e->getMessage(),
            ]);
        }
    }    
}
