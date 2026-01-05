<?php
namespace App\Services;

use App\Models\MqttApsLiveStatus;
use App\Models\MqttApsResponse;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Illuminate\Support\Facades\Log;


class ApMqttService
{
    protected MqttClient $mqtt;
    protected ConnectionSettings $connectionSettings;

    protected ?string $lastMessage = null;
    protected $port;
    protected $group;

    public function __construct()
    {
        // $host = env('MQTT_HOST', 'localhost');
        // $port = (int) env('MQTT_PORT', 1883);
        // $username = env('MQTT_USERNAME');
        // $password = env('MQTT_PASSWORD');
        // $clientId = 'ImmunityNetworksConfig-' . uniqid();
        // $useTls = filter_var(env('MQTT_TLS', false), FILTER_VALIDATE_BOOLEAN);
        
        $this->port = env('MQTT_PORT');
        $this->group = env('MQTT_GROUP');

        $host = env('MQTT_HOST', 'localhost');
        $useTls = filter_var(env('MQTT_TLS', false), FILTER_VALIDATE_BOOLEAN);

        // If TLS is enabled, use port 8883, otherwise use 1883 (default)
        $port = $useTls
            ? (int) env('MQTT_PORT', 8883) // Default to 8883 if TLS is on
            : (int) env('MQTT_PORT', 1883); // Default to 1883 for non-TLS

        $username = env('MQTT_USERNAME');
        $password = env('MQTT_PASSWORD');
        $clientId = 'ImmunityNetworksConfig-' . uniqid();

        
        $this->connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls($useTls)
            ->setKeepAliveInterval(10)
            ->setLastWillTopic('clients/disconnects')
            ->setLastWillMessage("Client $clientId disconnected unexpectedly");

        // Add TLS certificate configuration if needed
        if ($useTls) {
            $this->connectionSettings
                ->setTlsCertificateAuthorityFile('/path/to/ca.pem'); // Path to CA certificate
        }

        $this->mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_3_1, new MemoryRepository());
        // $this->mqtt = new MqttClient($host, $port, $clientId, MqttClient::MQTT_5_0, new MemoryRepository());

    }

    public function connect(): void
    {
        try {
            // false = persistent session; true = clean session
            $cleanSession = true;

            $this->mqtt->connect($this->connectionSettings, $cleanSession);
        } catch (MqttClientException $e) {
            throw new \Exception("MQTT connection failed: " . $e->getMessage());
        }
    }

    public function publish(string $topic, string $message, int $qos = MqttClient::QOS_AT_LEAST_ONCE, bool $retain = true): void
    {
        try {
            $this->mqtt->publish($topic, $message, $qos, $retain);
        } catch (MqttClientException $e) {
            throw new \Exception("MQTT publish failed: " . $e->getMessage());
        }
    }


    public function subscribe(string $topic, callable $callback, int $qos = MqttClient::QOS_AT_LEAST_ONCE): void
    {
        try {
            // Log::info("enter subscribe");
            ini_set('max_execution_time', '0'); 
            $this->mqtt->subscribe($topic, $callback, 0);
            // $this->mqtt->loop(true);
        } catch (MqttClientException $e) {
            throw new \Exception("MQTT subscribe failed: " . $e->getMessage());
        }
    }
    
    public function loopOnce(bool $allowSleep = false, bool $allowSignalDispatch = true): void
    {
        try {
            // internally just calls php-mqtt/client -> loop()
            $this->mqtt->loop($allowSleep, $allowSignalDispatch);
        } catch (MqttClientException $e) {
            throw new \Exception("MQTT loop failed: " . $e->getMessage());
        }
    }

    public function disconnect(): void
    {
        try {
            $this->mqtt->disconnect();
        } catch (MqttClientException $e) {
            throw new \Exception("MQTT disconnect failed: " . $e->getMessage());
        }
    }


    public function waitForResponse(string $topic, int $timeout = 60): ?array
    {
        $this->lastMessage = null;

        $this->subscribe($topic, function (string $topic, string $message) {
            // Log::info("Received on $topic: $message");
            $this->lastMessage = $message; // store on class property
        }, 1);

        $start = time();
        while ($this->lastMessage === null && (time() - $start) < $timeout) {
        // while ($this->lastMessage === null) {
            try {
                $this->mqtt->loopOnce(true); // process one MQTT cycle
            } catch (\Throwable $e) {
                Log::error("Loop error: " . $e->getMessage());
                break;
            }
            usleep(100000); // 0.1s
        }
        // return $this->lastMessage ? json_decode($this->lastMessage, true) : null;
        $reply = $this->lastMessage ? json_decode($this->lastMessage, true) : null;
        if ($reply) {
            $heartbeat = MqttApsLiveStatus::where('mac', $reply['mac'])->first();
            if ($heartbeat) {
                Log::info("reply table", ['reply row' => $reply]);
                $heartbeat->update([
                    'setting_update_at' => now()
                ]);
                MqttApsResponse::create([
                    'mac' => $reply['mac'],
                    'json_response' => json_encode($reply),
                    'from_response' => "mqtt",
                ]);                
            } 
        }
        return $this->lastMessage ? json_decode($this->lastMessage, true) : null;
    }


    public function waitForResponseSingal(string $topic, int $timeout = 60, bool $clearOld = true): ?array
    {
        $this->lastMessage = null;

        $this->subscribe($topic, function (string $topic, string $message) {
            // Log::info("Received on $topic: $message");
            $this->lastMessage = $message; // store on class property
        }, 1);

        $start = time();
        // while ($this->lastMessage === null && (time() - $start) < $timeout) {
        while ($this->lastMessage === null) {
            try {
                $this->mqtt->loopOnce(true); // process one MQTT cycle
            } catch (\Throwable $e) {
                Log::error("Loop error: " . $e->getMessage());
                break;
            }
            usleep(100000); // 0.1s
        }
        // return $this->lastMessage ? json_decode($this->lastMessage, true) : null;
        $reply = $this->lastMessage ? json_decode($this->lastMessage, true) : null;
        if ($reply) {
            $heartbeat = MqttApsLiveStatus::where('mac', $reply['mac'])->first();
            if ($heartbeat) {
                Log::info("reply table", ['reply row' => $reply]);
                $heartbeat->update([
                    'setting_update_at' => now()
                ]);
                MqttApsResponse::create([
                    'mac' => $reply['mac'],
                    'json_response' => json_encode($reply),
                    'from_response' => "mqtt",
                ]);                
            } 
        }
        return $reply;
    }


    public function pingDevice(string $mac, int $timeout = 30, string $pingTopic, string $pongTopic)
    {
        $mac = strtoupper(str_replace(':', '-', $mac)); // Ensure format matches AP
        // $pingTopic = "ap/{$mac}/ping";
        // $pongTopic = "ap/{$mac}/pong";

        $this->lastMessage = null;

        // Publish ping (can be empty or with timestamp)
        $payload = json_encode(['ping' => true, 'timestamp' => time()]);
        $this->publish($pingTopic, $payload);

        $this->subscribe($pongTopic, function (string $topic, string $message) {
            $this->lastMessage = $message; // store on class property
        }, 1);

        $start = time();
        while ($this->lastMessage === null && (time() - $start) < $timeout) {
        // while ($this->lastMessage === null) {
            try {
                $this->mqtt->loopOnce(true); // process one MQTT cycle
            } catch (\Throwable $e) {
                Log::error("Loop error: " . $e->getMessage());
                break;
            }
            usleep(100000); // 0.1s
        }

        return $this->lastMessage !== null;
    }


}

