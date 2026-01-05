<?php

namespace App\Http\Requests;

use App\Models\Router;
use App\Models\SupportTicket;
use App\Models\ZohoOauthToken;
use App\Services\ZohoAuth;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupportForm extends BaseRequest
{

    protected $router;
    protected $retry = 0;
    protected $supportTicket;

    public function authorize()
    {
        return true;
    }


    public function rules()
    {
        return [
            'subject' => 'required|max:150',
            'message' => 'required|max:500',
            'router_id' => 'nullable|integer|exists:routers,id',
            'name' => 'nullable|max:150',
            'phone' => 'nullable|digits_between:8,12',
            'email' => 'nullable|email',
        ];
    }

    public function save()
    {
        if ($this->router_id) {
            $this->router = Router::find($this->router_id);
        }

        DB::beginTransaction();
        $this->supportTicket = new SupportTicket();
        $this->supportTicket->fill($this->validated());
        if (Auth::user()) {
            $this->supportTicket->user_id = Auth::id();
        }
        $this->supportTicket->save();

        $this->addToZoho();

        DB::commit();

        return $this->supportTicket;

    }

    protected function addToZoho()
    {
        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }

        $organization = config('services.zoho.organization_id');
        $department = config('services.zoho.department_id');
        $desk_user = config('services.zoho.desk_user_id');

        $data = collect($this->supportTicket->toArray())->only('subject', 'name', 'email', 'phone');
        $data['description'] = $this->supportTicket->message;
        if ($this->router) {
            if ($this->router->zoho_desk_product_id) {
                $data['productId'] = $this->router->zoho_desk_product_id;
            }
            $data['cf'] = [
                'productName' => $this->router->name,
                'macAddress' => $this->router->mac_address,
                'zoho_inventory_id' => $this->router->zoho_inventory_id,
            ];
        }
        $data['departmentId'] = $department;
        $data['channel'] = 'Immunity Network Panel';

        Log::info($data);

        $user = Auth::user();
        $contact = [
            'firstName' => $this->get('name', $user->first_name),
            'phone' => $this->get('name', $user->phone),
            'email' => $this->get('name', $user->email),
        ];
        $data['contact'] = $contact;

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token,
            'orgId' => $desk_user,
        ])->connectTimeout(30)->timeout(30)->post('https://desk.zoho.in/api/v1/tickets', $data);

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        Log::info($response->body());
        Log::info($response->status() . ' - HTTP');
        if (!$response->successful()) {
            $this->retryOrFail($response);
        }

        $id = $response->json('id');
        if ($id) {
            $this->supportTicket->zoho_reference = $id;
            $this->supportTicket->save();
        }
    }

    protected function retryOrFail(Response $response)
    {
        if ($this->retry > 0 || $response->status() != 401) {
            abort($response->status(), 'ZOHO ERROR: ' . $response->json('message'));
        }

        Log::info('REFRESH ACCESS TOKEN');

        $this->retry++;

        if (!app()->make(ZohoAuth::class)->refreshToken()) {
            abort($response->status(), 'ZOHO ERROR: ' . $response->json('message'));
        }

        $this->addToZoho();
    }
}
