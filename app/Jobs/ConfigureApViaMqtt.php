<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\MqttApsLiveStatus;
use App\Models\MqttApsResponse;
use App\Services\ApMqttService;

class ConfigureApViaMqtt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $device;
    protected $port;
    protected $group;

    public function __construct(array $device)
    {
        $this->device = $device;
        $this->port = env('MQTT_PORT');
        $this->group = env('MQTT_GROUP');
    }
    
    public function handle()
    {
        $mac = $this->device['mac'];
        $group = $this->group;
        if (!$mac) {
            Log::warning("Missing MAC in device payload", ['device' => $this->device]);
            return;
        }

        $topic = "ap/{$group}/{$mac}/settings";
        $responseTopic = "ap/{$group}/{$mac}/response";
        // $topic = "ap/{$mac}/settings";
        // $responseTopic = "ap/{$mac}/response";
    
        try {

            $mqtt = app(ApMqttService::class);
            Log::info("this device data", ['this_device'=> $this->device]);
            
            $mqtt->connect();
            Log::info("connect mqtt");

            $pingTopic = "ap/{$group}/{$mac}/ping";
            $pongTopic = "ap/{$group}/{$mac}/pong";
            
            $pongResponse = $mqtt->pingDevice($mac, 10, $pingTopic, $pongTopic);
            if (!$pongResponse) {
                Log::warning("Device not online or did not respond to ping", ['mac' => $mac]);
                $mqtt->disconnect();
                MqttApsResponse::create([
                    'mac' => $mac,
                    'success' => false,
                    'from_response' => "mqtt",
                    'json_response' => json_encode(['message' => 'Device not online or did not respond']),
                ]);
                return; // or handle as offline
            }

            $mqtt->publish($topic, json_encode($this->device));
            Log::info("publish message");
            
            $response = $mqtt->waitForResponse($responseTopic, 50);
            Log::info("subscribe and rececice message");
            
            $mqtt->disconnect();
            Log::info("disconnect mqtt");
                
            if ($response) {
                MqttApsLiveStatus::where('mac', $response['mac'])->update([
                    'setting_update_at' => now()
                ]);
    
                MqttApsResponse::create([
                    'mac' => $response['mac'],
                    'success' => true,
                    'from_response' => "mqtt",
                    'json_response' => json_encode($response),
                ]);
                
                Log::info("this device data", [
                    'mac' => $mac,
                    'success' => true,
                    'response' => $response,
                ]);
            } else {
                MqttApsResponse::create([
                    'mac' => $response['mac'],
                    'success' => false,
                    'from_response' => "mqtt",
                    'json_response' => json_encode(['message' => 'not applicable']),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("MQTT job failed", [
                'mac' => $mac,
                'error' => $e->getMessage()
            ]);
        }
    }
}
