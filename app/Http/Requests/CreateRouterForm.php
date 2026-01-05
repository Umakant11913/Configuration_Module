<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\Router;
use App\Models\User;
use App\Models\ZohoOauthToken;
use App\Services\ZohoAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class CreateRouterForm extends BaseRequest
{
    protected $router;
    protected $retry = 0;

    protected function setup()
    {
        $this->router = new Router();
        if ($this->id) {
            $this->router = Router::findOrFail($this->id);
        }
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|max:150',
            //'model_id' => 'required',
            'mac_address' => 'required|max:150|unique:routers',
            'serial_number' => 'required|max:150|unique:routers',
            'location_id' => 'nullable|integer|exists:locations,id',
            'owner_id' => 'nullable|integer|exists:users,id',
            'upload_speed' => 'nullable|integer',
            'download_speed' => 'nullable|integer',
            'status' => 'nullable|integer',
            'is_active' => 'nullable|integer',
            'wifi_configuration_profile_id'
        ];
        if ($this->id) {
            $rules['mac_address'] = 'nullable|max:150|unique:routers,mac_address,' . $this->id;
            $rules['serial_number'] = 'nullable|max:150|unique:routers,serial_number,' . $this->id;
        }
        return $rules;
    }

    private function routerData()
    {
        if ($this->id) {
            $data = $this->only('name', 'model_id', 'upload_speed', 'download_speed', 'mac_address', 'eth1', 'serial_number', 'wifi_configuration_profile_id','wireless1','wireless2');
        } else {
            $data = $this->only('name', 'model_id', 'mac_address', 'eth1', 'wireless1', 'wireless2', 'serial_number', 'upload_speed', 'download_speed', 'is_active','wifi_configuration_profile_id');
            $data['macAddress'] = $data['mac_address']; //eth0
            $data['eth1'] = $data['eth1']; //eth1
            $data['wireless1'] = $data['wireless1']; // wireless 2.4
            $data['wireless2'] = $data['wireless2']; // wireless 2.5
        }

        if ($this->location_id) {
            $data['location_id'] = $this->location_id;
            $location = Location::find($this->location_id);
            $data['owner_id'] = $location->owner_id;
        } else if ($this->owner_id) {
            $data['owner_id'] = $this->owner_id;
        }
        $data['model_id'] = $this->model_id;

        return $data;
    }

    public function save()
    {
        $data = $this->routerData();
        $this->router->fill($data);
        $this->router->status = $this->get('status', 1);
        DB::beginTransaction();
        if (!$this->router->secret) {
            $this->router->secret = Str::random(30);
        }
        if (!$this->router->key) {
            $this->router->key = Str::random(20);
        }
        $this->router->save();

        $this->addSerialItemToZoho();
        DB::commit();
        return $this->router;
    }

    protected function addItemToZoho()
    {
        if (!config('services.zoho.enabled')) {
            return;
        }
        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }

        $organization = config('services.zoho.organization_id');

        $data = $this->router->toArray();
        $data['status'] = 'active';
        $data['part_number'] = $data['mac_address'];

        if ($this->router->zoho_inventory_id) {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token
            ])->connectTimeout(30)->timeout(30)->put('https://inventory.zoho.in/api/v1/items/' . $this->router->zoho_inventory_id . '?organization_id=' . $organization, $data);
        } else {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token
            ])->connectTimeout(30)->timeout(30)->post('https://inventory.zoho.in/api/v1/items?organization_id=' . $organization, $data);
        }
        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }

        if (!$response->successful()) {
            $this->retryOrFail($response);
        }
        $item = $response->json('item');
        if ($item) {
            $this->router->zoho_inventory_id = $item['item_id'];
            $this->router->save();
        }
        $this->retry = 0;

        $this->addToZohoDesk();
    }

    protected function addSerialItemToZoho()
    {
        if (!config('services.zoho.enabled')) {
            return;
        }
        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }

        if ($this->router->zoho_inventory_id) {
            return;
        }

        $organization = config('services.zoho.organization_id');
        $item_id = config('services.zoho.item_id');
        $adjustment_account = config('services.zoho.adjustment_account');
        $warehouse_id = config('services.zoho.warehouse_id');

        $data = [
            'date' => Carbon::today()->format('Y-m-d'),
            'reason' => 'New Inventory',
            'reference_number' => 'WifiAdmin-' . $this->router->id,
            'adjustment_type' => 'quantity',
        ];

        $data['line_items'][] = [
            'item_id' => $item_id,
            'quantity_adjusted' => 1,
            'adjustment_account_id' => $adjustment_account,
            'serial_numbers' => [$this->router->serial_number],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token
        ])->connectTimeout(30)->timeout(30)->post('https://inventory.zoho.in/api/v1/inventoryadjustments?organization_id=' . $organization, $data);

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        if (!$response->successful()) {
            $this->retryOrFail($response);
        }
        $item = $response->json('inventory_adjustment');
        if ($item) {
            $this->router->zoho_inventory_id = $item['inventory_adjustment_id'];
            $this->router->save();
        }
        $this->retry = 0;

        if ($this->router->location_id || $this->router->owner_id) {
            $this->decreaseAdjustment();
        }
//        $this->addToZohoDesk();
    }

    protected function decreaseAdjustment()
    {
        $oauthToken = ZohoOauthToken::latest()->first();

        $organization = config('services.zoho.organization_id');
        $item_id = config('services.zoho.item_id');
        $adjustment_account = config('services.zoho.adjustment_account');
        $warehouse_id = config('services.zoho.warehouse_id');

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $owner = $user;
        $reason = 'Assigned to ';
        if ($this->router->location_id) {
            $location = Location::find($this->router->location_id);
            $owner = $location->owner;
            $reason .= $location->name . ' of ' . $owner->full_name;
        } else if ($this->router->owner_id) {
            $owner = User::find($this->router->owner_id);
            $reason .= $owner->full_name;
        }


        $reference = 'Owner-' . $owner->id;

        $data = [
            'date' => Carbon::today()->format('Y-m-d'),
            'reason' => $reason,
            'reference_number' => $reference,
            'adjustment_type' => 'quantity',
        ];

        $data['line_items'][] = [
            'item_id' => $item_id,
            'quantity_adjusted' => -1,
            'adjustment_account_id' => $adjustment_account,
            'serial_numbers' => [$this->router->serial_number],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token
        ])->connectTimeout(30)->timeout(30)->post('https://inventory.zoho.in/api/v1/inventoryadjustments?organization_id=' . $organization, $data);

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        if (!$response->successful()) {
            $this->retryOrFail($response, 'decreaseAdjustment');
        }
        $item = $response->json('inventory_adjustment');
        $this->retry = 0;
    }

    protected function addToZohoDesk()
    {
        if (!config('services.zoho.enabled')) {
            return;
        }

        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }

        $organization = config('services.zoho.organization_id');
        $department = config('services.zoho.department_id');
        $desk_user = config('services.zoho.desk_user_id');

        if (!$department || !$desk_user) {
            return;
        }

        $data = collect(['productName' => $this->router->name]);
        $data['cf'] = [
            'macAddress' => $this->router->mac_address,
            'zoho_inventory_id' => $this->router->zoho_inventory_id,
        ];
        $data['departmentIds'] = [$department];

        if ($this->router->zoho_desk_product_id) {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token,
                'orgId' => $desk_user,
            ])->connectTimeout(30)->timeout(30)->post('https://desk.zoho.in/api/v1/products/' . $this->router->zoho_desk_product_id, $data);
        } else {
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token,
                'orgId' => $desk_user,
            ])->connectTimeout(30)->timeout(30)->post('https://desk.zoho.in/api/v1/products', $data);
        }

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        if (!$response->successful()) {
            $this->retryOrFail($response, 'product');
        }

        $id = $response->json('id');
        if ($id) {
            $this->router->zoho_desk_product_id = $id;
            $this->router->save();
        }
    }

    protected function retryOrFail(Response $response, $type = 'inventory')
    {
        if ($this->retry > 0 || $response->status() != 401) {
            abort($response->status(), 'ZOHO ERROR: ' . $response->json('message'));
        }

        $this->retry++;

        if (!app()->make(ZohoAuth::class)->refreshToken()) {
            abort($response->status(), 'ZOHO ERROR: ' . $response->json('message'));
        }

        if ($type == 'inventory') {
            $this->addItemToZoho();
        } else if ($type == 'decreaseAdjustment') {
            $this->decreaseAdjustment();
        } else {
            $this->addToZohoDesk();
        }
    }
}
