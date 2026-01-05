<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Models\AppProviderRegistry;
use App\Models\Location;
use App\Models\NetworkSettings;
use App\Models\PdoaPlan;
use App\Models\PdoaRegistry;
use App\Models\PdoCredits;
use App\Models\Router;
use App\Models\User;
use App\Services\CryptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaptiveController extends Controller
{
    protected $cryptService;

    public function __construct(CryptService $cryptService)
    {
        $this->cryptService = $cryptService;
    }

    public function forward(Request $request)
    {
        $waniapptoken = $request->get('waniapptoken');
        if (!$waniapptoken) {
            abort(422, 'No Token received');
        }

        list($providerId, $waniapptokenCipher) = explode('|', $waniapptoken);

        if (!$providerId || !$waniapptokenCipher) {
            abort(422, 'Malformed Token');
        }

        $providerRegistry = AppProviderRegistry::where('provider_id', $providerId)->first();
        if (!$providerRegistry) {
            abort(422, 'Unknown App Provider');
        }

        // Check pdoa presence in Wani Registry for confirmation
        $pdoaProviderId = config('services.wani.captive.pdoa_registry_id');
        $pdoaRegistryEntry = PdoaRegistry::query()->where('provider_id', $pdoaProviderId)->first();
        if (!$pdoaRegistryEntry) {
            abort(500, 'PDOA Entry not in wani registry!');
        }
        $publicKey = $pdoaRegistryEntry->key()->first();

        if (!$publicKey) {
            abort(500, 'PDOA Keys not set!');
        }

        $expiresOn = $publicKey->expires_on;
        $pdoaProviderPrivateKey = storage_path(config('services.wani.captive.pdoa_private_key'));
        $pdoaProviderPrivatePassword = config('services.wani.captive.pdoa_private_key_password');

        $cipherText = $this->cryptService->chunkAndEncryptUsingPrivateKeyFromFile($waniapptoken, $pdoaProviderPrivateKey, $pdoaProviderPrivatePassword);

        $base64CipherText = base64_encode($cipherText);
        // dd($waniapptoken, $cipherText, $base64CipherText, $this->cryptService->decryptUsingPublicKey($cipherText, $pdoaProvider->public_key));

        $authUrl = $providerRegistry->auth_url;

        $wanipdoatoken = $pdoaProviderId . '|' . $expiresOn . '|' . $base64CipherText;

        $utf8EncodedToken = urlencode(utf8_encode($wanipdoatoken));
        $response = Http::timeout(10)->acceptJson()->get($authUrl . '?wanipdoatoken=' . $utf8EncodedToken);

        if (!$response->successful()) {
            abort($response->status(), $response->reason());
        }

        $profileResponse = $response->json();

        $resp = collect($profileResponse);
        $hashItems = $resp->only(
            'timestamp',
            'Username',
            'password',
            'apMacId',
            'payment-address',
            'deviceMacId'
        )->toArray();
        $hashString = implode('', array_values($hashItems));
        $computedSignature = hash('sha256', $hashString);

        $appProviderId = $profileResponse['app-provider-id'];

        $providerRegistry = AppProviderRegistry::where('provider_id', $appProviderId)->first();

        if (!$providerRegistry) {
            abort(422, 'Unknown App Provider In Response');
        }

        $appPublicKey = $providerRegistry->key()->first()->key ?? false;

        if (!$appPublicKey) {
            abort(500, 'No Public Key found in Registry For App Provider Response');
        }

        $receivedSignature = $this->cryptService->chunkAndDecryptUsingPublicKey($profileResponse['signature'], $appPublicKey);

        if ($computedSignature != $receivedSignature) {
            //            abort(401, "Invalid Signature");
        }

        $registerRequest = new \Illuminate\Http\Request();
        $registerRequest->replace([
            'Username' => $profileResponse['Username'],
            'password' => $profileResponse['password'],
            'app_id' => $profileResponse['app-provider-id'],
        ]);
        $response = app(RegisteredUserController::class)->register_pmwwani_user($registerRequest);

        $user_token = $response['token'];

        //$profileResponse['payment-address'] =  "http://172.22.100.1:3990/logoff?token=".$this->jwt($user->toArray());
        //$profileResponse['paymentUrl'] =  "https://wifilogin.immunitynetworks.com/applogin?token=".$this->jwt($user->toArray());
        // $profileResponse['paymentUrl'] = "https://wifilogin.immunitynetworks.com/applogin?token=" . $user_token;

        $network_settings = NetworkSettings::first();
        $loginUrl = $network_settings->loginUrl;
        $profileResponse['paymentUrl'] = "https://" . $loginUrl . "/applogin?token=" . $user_token;

        return $profileResponse;
    }

    public function showServiceError(Request $request)
    {
        $locationId = $request->location_id;

        $macAddress = $request->mac_address;
        if ($locationId !== null && $locationId !== 0 ) {
            $routerDetails = Router::where('mac_address', $macAddress)->orWhere('eth1', $macAddress)->first();

            if ($routerDetails->is_active === 0) {
                return response()->json([
                    'msg' => 'services not available',
                    'status' => false,
                ], 200);
            }
        }
            return response()->json([
                'msg' => 'services available',
                'status' => true,
            ], 200);
    }
}
