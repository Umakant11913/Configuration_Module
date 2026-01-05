<?php

namespace App\Jobs;

use App\Models\Location;
use App\Models\User;
use App\Models\ZohoOauthToken;
use App\Services\ZohoAuth;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UpdatePayableToZohoBook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $owner;
    protected $vendor_amount;
    protected $retry = 0;
    protected $contact;
    protected $order;
    protected $payout;

    public function __construct(public $payouts)
    {
        //Log::info('constructed');
    }


    public function handle()
    {
        foreach ($this->payouts as $payout) {
            $user_id = $payout->owner_id;
            $commission_amount = $payout->payout_amount;

            $this->contact = null;
            $this->owner = User::find($user_id);
            $this->order = 'Payout for order: ' . $payout->order->id;
            $this->vendor_amount = $commission_amount;
            $this->payout = $payout;
            $this->addToZohoBook();
        }
    }

    protected function addToZohoBook()
    {
        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }

        $organization_id = config('services.zoho.organization_id');
        $email_contains = $this->owner->email;

        $params = http_build_query(compact('organization_id', 'email_contains'));

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token,
        ])->connectTimeout(30)
            ->timeout(30)
            ->get('https://inventory.zoho.in/api/v1/contacts?' . $params);

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        Log::info($response->status() . ' - HTTP');
        if (!$response->successful()) {
            $this->retryOrFail($response);
        }

        $this->retry = 0;

        $contacts = $response->json('contacts');

        foreach ($contacts as $contact) {
            if ($contact['contact_type'] == 'vendor') {
                $this->contact = $contact;
            }
        }
        if ($this->contact) {
            $this->addBillInZoho();
        } else {
            $this->createContact();
        }

    }

    protected function createContact()
    {
        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }
        $organization_id = config('services.zoho.organization_id');

        $params = http_build_query(compact('organization_id'));

        $data = [
            'contact_name' => $this->owner->first_name . ' ' . $this->owner->last_name,
            'contact_type' => 'vendor',
            'contact_persons' => [
                [
                    'first_name' => $this->owner->first_name,
                    'last_name' => $this->owner->last_name,
                    'email' => $this->owner->email,
                    'phone' => $this->owner->phone,
                    'is_primary_contact' => true,
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token,
        ])->connectTimeout(30)
            ->timeout(30)
            ->post('https://inventory.zoho.in/api/v1/contacts?' . $params, $data);

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        Log::info($response->status() . ' - HTTP');
        if (!$response->successful()) {
            $this->retryOrFail($response, 'contact');
        }

        $this->retry = 0;
        Log::info($response->body());
        $this->contact = $response->json('contact');
        $this->addBillInZoho();
    }

    protected function addBillInZoho()
    {
        $oauthToken = ZohoOauthToken::latest()->first();
        if (!$oauthToken) {
            abort('422', 'Please link zoho');
        }
        $organization_id = config('services.zoho.organization_id');

        $params = http_build_query(compact('organization_id'));

        $data = [
            'vendor_id' => $this->contact['contact_id'],
            'vendor_name' => $this->contact['vendor_name'] ?? $this->contact['contact_name'],
            'bill_number' => time() . Str::random(5),
            'source_of_supply' => 'MH',
            'destination_of_supply' => 'MH',
            'gst_treatment' => 'business_none',
            'line_items' => [
                [
                    'account_id' => '879364000000000558',
                    'account_name' => 'Other Expenses',
                    'description' => 'Commission for Order# ' . $this->order,
                    'quantity' => 1,
                    'rate' => $this->vendor_amount,
                ],
            ],
            'sub_total' => $this->vendor_amount,
            'tax_total' => $this->vendor_amount,
            'total' => $this->vendor_amount,
            'notes' => 'Commission for Order# ' . $this->order,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $oauthToken->access_token,
        ])->connectTimeout(30)
            ->timeout(30)
            ->post('https://inventory.zoho.in/api/v1/bills?' . $params, $data);

        if (!$response) {
            abort(400, 'Unable to Link Zoho');
        }
        Log::info($response->body());
        Log::info($response->status() . ' - HTTP');
        if (!$response->successful()) {
            $this->retryOrFail($response, 'bill');
        }

        $this->retry = 0;
        Log::info($response->body());
        $bill = $response->json('bill');
        if ($response->json('bill') && $this->payout) {
            $this->payout->zoho_reference_id = $bill['bill_id'];
            $this->payout->save();
        }
    }

    protected function retryOrFail(Response $response, $type = 'inventory')
    {
        if ($this->retry > 0 || $response->status() != 401) {
            abort($response->status(), "Type: $type - " . 'ZOHO ERROR: ' . $response->json('message'));
        }

        $this->retry++;

        if (!app()->make(ZohoAuth::class)->refreshToken()) {
            abort($response->status(), 'ZOHO ERROR: ' . $response->json('message'));
        }

        if ($type == 'bill') {
            $this->addBillInZoho();
        } else if ($type == 'contact') {
            $this->createContact();
        } else {
            $this->addToZohoBook();
        }
    }
}
