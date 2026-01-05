<?php

namespace App\Http\Requests;

use App\Events\PdoSmsQuotaEvent;
use App\Listeners\SendPdoLocationEmailListeners;
use App\Models\Location;
use App\Models\NotificationSettings;
use App\Models\PdoaPlan;
use App\Models\PdoSettings;
use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Geocoder\Exceptions\CouldNotGeocode;
use Spatie\Geocoder\Facades\Geocoder;
use App\Events;
use App\Events\PdoLocationEvent;


class CreateLocationForm extends BaseRequest
{
    protected $location;
    protected $user;

    public function authorize()
    {
        $user = $this->user;
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $this->setup();
        if (!$this->location->id) {
            return true;
        }
        return $user->isAdmin() || $user->id == $this->location->owner_id;
    }

    public function rules()
    {
        $user = $this->user;
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $rules = [
            'name' => 'required|max:150',
            'address' => 'required|max:255',
            'city' => 'required|max:100',
            'state' => 'required|max:50',
            'postal_code' => 'required|max:15',
            'email' => 'required|email',
            'phone' => 'required|digits_between:8,12',
            'owner_id' => 'required|exists:users,id',
            'free_session_bandwidth_mbps' => 'nullable|numeric',
            'free_session_duration_in_mins' => 'nullable|numeric',
            'routers' => 'nullable|array',
            'personal_essid' => 'nullable|max:255',
            'personal_essid_password' => 'nullable|max:255',
            'wifi_configuration_profile_id',
            'assigned_at' =>'nullable|numeric',
        ];
        if (!$user->isAdmin()) {
            unset($rules['owner_id']);
        }
        return $rules;
    }

    protected function setup()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $this->user = $user;
        $this->location = new Location();
        if ($this->id) {
            $this->location = Location::findOrFail($this->id);
        }
        $this->setLocationOwner();
    }

    protected function setLocationOwner()
    {
        $user = $this->user;
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $this->location->owner_id = $user->isAdmin() ?
            $this->owner_id : $user->id;
    }

    protected function locationData()
    {
        return $this->only(
            'name',
            'email',
            'phone',
            'address',
            'city',
            'state',
            'postal_code',
            'free_session_duration_in_mins',
            'free_session_bandwidth_in_mbps',
            'personal_essid',
            'personal_essid_password',
            'wifi_configuration_profile_id',
            'snmp_profile_id',
            'ntp_profile_id',
            'qos_profile_id',
            'network_profile_id',
            'domainfilter_profile_id',  
            'license_key',
            'assigned_at'
        );
    }

    public function save()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $isDirty = false;
        DB::beginTransaction();
        $oldConfigurationId = "";
        $oldConfiguration = Location::where('id', $this->location->id)->first();
        if($oldConfiguration){
            $oldConfigurationId = $oldConfiguration->wifi_configuration_profile_id;
        }
        $this->location->fill($this->locationData());
        if ($this->location->isDirty()) {
            $isDirty = true;
        }
        $this->location->added_by = $this->location->added_by ?? $user->id;
        $this->location->license_key = Hash::make($this->location->license_key);
        $this->location->assigned_at = Carbon::now();
        $this->location->save();

        $user = User::where('id',$this->location->owner_id)->first();

        $notification = NotificationSettings::where('pdo_id',$this->location->owner_id)->where('notification_type','location')
            ->where('frequency','on-event')->first();
        if($notification) {
        event(new PdoLocationEvent($user, $this->location, $notification));
        } else {
            $notification = $this->notification = null;
            event(new PdoLocationEvent($user, $this->location, $notification));
        }

        $oldRouters = Router::where('location_id', $this->location->id)->pluck('id')->toArray();

        $routersList = $this->get('routers', []);

        $newRouters = array_diff($routersList, $oldRouters);
        $newRoutersList = Router::whereIn('id', $newRouters)->get();

        if ($this->id) {
            $this->location->routers()->update(['location_id' => null]);
        }

        $routers = Router::whereIn('id', $this->get('routers', []))->get();
        if (count($routers) > 0) {
            if(count($newRouters) > 0) {
                $isDirty = true;
            }
            $routers->toQuery()->update(['location_id' => $this->location->id ,'owner_id' =>$this->location->owner_id, 'pdo_id' =>$this->location->owner_id, 'retired' => 0]);
            if ($isDirty) {
                $pdo = User::where('id', $this->location->owner_id)->first();
                if($pdo->auto_renew_subscription === 1){
                    foreach($newRoutersList as $router) {
                        if($router->is_active === true || $router->is_active === null) {
                            $router->is_active = 0;
                            $router->save();
                        }
                    }
                }
                /*else {
                    foreach($newRoutersList as $router) {
                        if($router->is_active === true || $router->is_active === 0 || $router->auto_renewal_date !== null || $router->original_renewal_date !== null) {
                            $router->is_active = null;
                            $router->auto_renewal_date = null;
                            $router->original_renewal_date = null;
                            $router->save();
                        }
                    }
                }*/
                //$routers->toQuery()->increment('configurationVersion');
                if(isset($oldConfigurationId) && $oldConfigurationId != "" && $oldConfigurationId !== $this->location->wifi_configuration_profile_id) {
                    $routers = Router::where('location_id' ,$this->location->id)->get();
                }

                $routers->toQuery()->increment('configurationVersion');
            }
        }

        //$this->assignSmsQuota($user);
        $this->setLatLng();
        DB::commit();

        return $this->location;
    }

    private function setLatLng()
    {
        $addressComponents = [
            $this->location->address,
            $this->location->city,
            $this->location->state,
            $this->location->postal_code,
        ];

        $addressComponents = array_filter($addressComponents, function ($item) {
            return $item;
        });

        $address = implode(', ', $addressComponents);
//        Log::info($address);
        try{
            $coordinate = Geocoder::getCoordinatesForAddress($address);
            if ($coordinate['accuracy'] != 'result_not_found') {
                $this->location->lat = $coordinate['lat'];
                $this->location->lng = $coordinate['lng'];
                $this->location->save();
            }
        }catch (CouldNotGeocode $e) {
            // echo "An error occurred: " . $e->getMessage();
            return true;
        }
    }

    private function assignSmsQuota($user)
    {
        $total_router = Router::where('owner_id', $user->id)->whereNotNull('location_id')->count();
        $pdoPlan = PdoaPlan::where('id', $user->pdo_type)->first();
        if ($pdoPlan) {
            $totalSmsCredits = $total_router * $pdoPlan->sms_quota;
            $pdoSettings = PdoSettings::where('pdo_id', $user->id)->first();
            if (!$pdoSettings) {
                $pdoSettings = new PdoSettings();
                $pdoSettings->pdo_id = $user->id;
                $pdoSettings->period_quota = $totalSmsCredits;
                $pdoSettings->save();
            } else {
                $pdoSettings->period_quota = $totalSmsCredits;
                $pdoSettings->save();
            }
            event(new PdoSmsQuotaEvent($user, $total_router, $pdoSettings));
        } else {
        }
    }
}
