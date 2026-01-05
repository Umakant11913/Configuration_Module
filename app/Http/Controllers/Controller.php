<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Profile;
use App\Models\Router;
use App\Models\User;
use App\Models\ZohoInvoiceLocation;
use App\Models\ZohoOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use ValidatesRequests;

    public function webhook(Request $request)
    {
        $response = json_decode($request->get('JSONString'), true);

        if ($response && $response['invoice']) {
            $this->processInvoice($response['invoice']);
        }
    }

    public function media($path, Request $request)
    {
        return response()->file(storage_path('app/' . $path));
    }

    private function processInvoice($invoice)
    {

        $shipping_address = $invoice['shipping_address'];
        $billing_address = $invoice['billing_address'];
        $email = $invoice['email'];
        $contact_persons = $invoice['contact_persons_details'];
        $contact_person = null;

        if (count($contact_persons) > 0) {
            // take primary contact
            $contact_person = array_filter($contact_persons, function ($item) {
                return $item['is_primary_contact'];
            });
            // if not take first contact
            $contact_person = $contact_person ? $contact_person[0] : $contact_persons[0];
        }

        $line_items = $invoice['line_items'];

        $user = User::where('email', $email)->first();

        if (!$contact_person) {
            return;
        }


        DB::beginTransaction();
        if (!$user) {
            $user = User::create([
                'first_name' => $contact_person['first_name'],
                'last_name' => $contact_person['last_name'],
                'email' => $email,
                'password' => bcrypt(Str::random(8)),
                'phone' => $contact_person['phone'] ?? $contact_person['mobile'],
                'role' => config('constants.roles.location_owner')
            ]);
        }

        $profile = $user->profile ?? new Profile();
        $profile->user_id = $user->id;
        if (!$profile->address && $billing_address) {
            $profile->fill([
                'address' => $billing_address['address'] ?? '',
                'city' => $billing_address['city'] ?? '',
                'postal_code' => $billing_address['zip'] ?? '',
            ]);
        }
        $profile->save();

        $admin = User::where('role', config('constants.roles.admin'))->first();

        $zohoLocation = ZohoInvoiceLocation::where('zoho_invoice_id', $invoice['invoice_id'])->first();
        $location = null;
        if ($zohoLocation) {
            $location = Location::find($zohoLocation->location_id);
        }
        if (!$location) {
            $location = Location::create([
                'name' => $user->first_name . ' Location',
                'address' => $shipping_address['address'],
                'city' => $shipping_address['city'],
                'state' => $shipping_address['state'],
                'postal_code' => $shipping_address['zip'],
                'owner_id' => $user->id,
                'added_by' => $admin->id,
            ]);
        }
        if (!$zohoLocation) {
            ZohoInvoiceLocation::create([
                'zoho_invoice_id' => $invoice['invoice_id'],
                'location_id' => $location->id,
            ]);
        }

        foreach ($line_items as $line_item) {
            foreach ($line_item['serial_numbers'] as $serial_number) {

                $router = Router::where('serial_number', $serial_number)->first();

                if (!$router) {
                    $router = Router::create([
                        'name' => $line_item['name'] . ' ' . $serial_number,
                        'mac_address' => $serial_number,
                        'macAddress' => $serial_number,
                        'serial_number' => $serial_number,
                        'zoho_inventory_id' => $line_item['item_id'],
                        'secret' => Str::random(30),
                        'key' => Str::random(20),
                    ]);
                }

                $router->fill([
                    'location_id' => $location->id,
                    'owner_id' => $user->id
                ]);
                $router->save();
            }
        }

        $zohoOrder = new ZohoOrder();
        $zohoOrder->salesorder_number = $invoice['invoice_number'];
        $zohoOrder->order_status = $invoice['status'];
        $zohoOrder->customer_name = $invoice['customer_name'];
        $zohoOrder->total_formatted = $invoice['total_formatted'];
        $zohoOrder->total = $invoice['total'];
        $zohoOrder->date = $invoice['date'];
        $zohoOrder->customer_id = $user->id;
        $zohoOrder->save();

        $shouldSendEmail = config('services.zoho.should_send_email');

        if ($user->wasRecentlyCreated && $shouldSendEmail) {
            $token = Password::getRepository()->create($user);
            $user->sendPasswordResetNotification($token);
        }
        DB::commit();
    }
}
