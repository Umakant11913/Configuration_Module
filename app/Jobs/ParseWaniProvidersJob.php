<?php

namespace App\Jobs;

use App\Models\AppProviderRegistry;
use App\Models\PdoaRegistry;
use App\Models\PdoaRouterRegistry;
use App\Models\PdoaRoutersRegistriesPivot;
use App\Models\WaniRegistry;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ParseWaniProvidersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        ini_set('max_execution_time', '0');
        $fullPath = $this->parseProvidersFromSource();

//        $fullPath = Storage::path('xmls/20220621035622_Nuv21.xml');
        $parsedData = $this->xmlFileToObject($fullPath);

        $waniRegistry = $parsedData->WANIREGISTRY;

        $metaData = [
            'last_updated' => $waniRegistry->LASTUPDATED,
            'ttl' => $waniRegistry->TTL,
            'file' => $fullPath,
        ];

        WaniRegistry::create($metaData);
        $this->populatePdoaRegistry($waniRegistry);
        $this->populateAppProviderRegistry($waniRegistry);
    }

//    private function xmlFileToObject($filePath)
//    {
//        $xml = simplexml_load_file($filePath);
//        $json = json_encode($xml);
//        dd($xml, $json);
//        return json_decode($json);
//    }

    /**
     * @throws \Exception
     */
    private function parseProvidersFromSource(): string
    {
        $response = Http::get(config('services.wani.providers'));
        if (!$response->successful()) {
            throw new \Exception("Unable to fetch Providers", $response->status());
        }

        $fileName = Carbon::now()->format('YmdHis') . '_' . Str::random(5) . '.xml';
        $path = 'xmls/' . $fileName;
        Storage::put($path, $response->body());

        return Storage::path($path);
    }

    private function populatePdoaRegistry($parsedData)
    {
        $pdoas = $parsedData->PDOAS->PDOA ?? [];

        foreach ($pdoas as $pdoa) {
            $registry = PdoaRegistry::where('provider_id', $pdoa->ID)->first();
            if (!$registry) {
                $registry = PdoaRegistry::create([
                    'provider_id' => $pdoa->ID,
                    'ap_url' => $pdoa->APURL,
                    'email' => $pdoa->EMAIL,
                    'name' => $pdoa->NAME,
                    'phone' => $pdoa->PHONE,
                    'rating' => $pdoa->RATING,
                    'status' => $pdoa->STATUS,
                ]);

                $keys = $pdoa->KEYS;
                foreach ($keys as $key) {
                    foreach ($key->KEY as $actualKey) {
                        $registry->keys()->create([
                            'key' => $actualKey->content,
                            'expires_on' => $actualKey->EXP
                        ]);
                    }
                }
            }

            $currentUrl = "https://pmwani.gov.in/wani/registry/wani_providers/" . $pdoa->ID . "/wani_aplist.xml";

            $this->routersData($currentUrl, $registry->id);
        }
    }

    protected function routersData($data, $registryId)
    {
        $parsedRoutersData = $this->xmlFileToObject($data);
        $waniRegistry = $parsedRoutersData->WANIAPLIST;

        $routersLocation = $waniRegistry->LOCATION ?? [];

        foreach ($routersLocation as $routersData) {
            $locationData = [
		'name' => $routersData->NAME ?? null,
                'state' => $routersData->STATE ?? null,
            ];

            $routersAP = $routersData->AP ?? [];
            foreach ($routersAP as $accessPoint) {
                if(isset($accessPoint->MACID)) {
                    $existingAPs = PdoaRouterRegistry::where('macid', $accessPoint->MACID)->first();
                    if (!$existingAPs) {
                        if (isset($accessPoint->GEOLOC)) {
                            $accessPointData = [
                                'geoLoc' => $accessPoint->GEOLOC,
                                'macid' => $accessPoint->MACID,
                                'ssid' => $accessPoint->SSID,
                                'status' => $accessPoint->STATUS,
                                'pdoa_registry_id' => $registryId,
                            ];
                            $locationData = array_merge($locationData, $accessPointData);
                            $routerRegistry = PdoaRouterRegistry::create($locationData);
                        }
                    }
                }
            }
        }
    }

    private function populateAppProviderRegistry($parsedData)
    {
        $appProviders = $parsedData->APPPROVIDERS->APPPROVIDER ?? [];

        foreach ($appProviders as $appProvider) {
            $appProviderId = $appProvider->ID;
            $checkAppProvider = AppProviderRegistry::where('provider_id', $appProviderId)->first();
            if (!$checkAppProvider) {
                $registry = AppProviderRegistry::create([
                    'provider_id' => $appProvider->ID,
                    'auth_url' => $appProvider->AUTHURL,
                    'email' => $appProvider->EMAIL,
                    'name' => $appProvider->NAME,
                    'phone' => $appProvider->PHONE,
                    'rating' => $appProvider->RATING,
                    'status' => $appProvider->STATUS,
                ]);

                $keys = $appProvider->KEYS;
                foreach ($keys as $key) {
                    foreach ($key->KEY as $actualKey) {
                        $registry->keys()->create([
                            'key' => $actualKey->content,
                            'expires_on' => $actualKey->EXP
                        ]);
                    }
                }


            }

        }
    }

    function xmlFileToObject($path)
    {
        $xmlString = file_get_contents($path);
        $array = $this->XMLtoArray($xmlString);
        return json_decode(json_encode($array));
    }

    function XMLtoArray($XML)
    {
        $xml_parser = xml_parser_create();
        xml_parse_into_struct($xml_parser, $XML, $vals);
        xml_parser_free($xml_parser);
        // wyznaczamy tablice z powtarzajacymi sie tagami na tym samym poziomie
        $_tmp = '';
        foreach ($vals as $xml_elem) {
            $x_tag = $xml_elem['tag'];
            $x_level = $xml_elem['level'];
            $x_type = $xml_elem['type'];
            if ($x_level != 1 && $x_type == 'close') {
                if (isset($multi_key[$x_tag][$x_level]))
                    $multi_key[$x_tag][$x_level] = 1;
                else
                    $multi_key[$x_tag][$x_level] = 0;
            }
            if ($x_level != 1 && $x_type == 'complete') {
                if ($_tmp == $x_tag)
                    $multi_key[$x_tag][$x_level] = 1;
                $_tmp = $x_tag;
            }
        }
        // jedziemy po tablicy
        foreach ($vals as $xml_elem) {
            $x_tag = $xml_elem['tag'];
            $x_level = $xml_elem['level'];
            $x_type = $xml_elem['type'];
            if ($x_type == 'open')
                $level[$x_level] = $x_tag;
            $start_level = 1;
            $php_stmt = '$xml_array';
            if ($x_type == 'close' && $x_level != 1)
                $multi_key[$x_tag][$x_level]++;
            while ($start_level < $x_level) {
                $php_stmt .= '[$level[' . $start_level . ']]';
                if (isset($multi_key[$level[$start_level]][$start_level]) && $multi_key[$level[$start_level]][$start_level])
                    $php_stmt .= '[' . ($multi_key[$level[$start_level]][$start_level] - 1) . ']';
                $start_level++;
            }
            $add = '';
            if (isset($multi_key[$x_tag][$x_level]) && $multi_key[$x_tag][$x_level] && ($x_type == 'open' || $x_type == 'complete')) {
                if (!isset($multi_key2[$x_tag][$x_level]))
                    $multi_key2[$x_tag][$x_level] = 0;
                else
                    $multi_key2[$x_tag][$x_level]++;
                $add = '[' . $multi_key2[$x_tag][$x_level] . ']';
            }
            if (isset($xml_elem['value']) && trim($xml_elem['value']) != '' && !array_key_exists('attributes', $xml_elem)) {
                if ($x_type == 'open')
                    $php_stmt_main = $php_stmt . '[$x_type]' . $add . '[\'content\'] = $xml_elem[\'value\'];';
                else
                    $php_stmt_main = $php_stmt . '[$x_tag]' . $add . ' = $xml_elem[\'value\'];';
                eval($php_stmt_main);
            }
            if (array_key_exists('attributes', $xml_elem)) {
                if (isset($xml_elem['value'])) {
                    $php_stmt_main = $php_stmt . '[$x_tag]' . $add . '[\'content\'] = $xml_elem[\'value\'];';
                    eval($php_stmt_main);
                }
                foreach ($xml_elem['attributes'] as $key => $value) {
                    $php_stmt_att = $php_stmt . '[$x_tag]' . $add . '[$key] = $value;';
                    eval($php_stmt_att);
                }
            }
        }
        return $xml_array;
    }
}
