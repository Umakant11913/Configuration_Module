<?php

namespace App\Http\Requests;

use App\Models\BrandingProfile;
use App\Models\InternetPlan;
use App\Models\Location;
use App\Models\PdoPaymentGateway;
use App\Models\Router;
use App\Models\User;
use App\Models\ZoneInternetPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CentralUserService;
use Illuminate\Http\Request;

class CreateBrandingProfileForm extends BaseRequest
{
    protected $location;

    public function rules()
    {
        $rules = [
            'name' => 'required|max:150',
            'description' => 'required|max:2550',
            'free_plan' => 'required',
            'default_plan' => 'required'
        ];
        /*if (!$this->user->isAdmin()) {
            unset($rules['owner_id']);
        }*/
        return $rules;
    }

    protected function profileData()
    {
        return $this->only(
            'name',
            'description',
            'free_plan',
            'default_plan'
        );
    }

    public function save()
    {
        //$isDirty = false;
        DB::beginTransaction();

        $folder = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/branding_profiles/';
        // return dd($folder);
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $profile = new BrandingProfile();
        // $user = Auth::user();
        // if ($user->parent_id) {
        //     $parent = User::where('id', $user->parent_id)->first();
        //     $user = $parent;
        // }
        $user = CentralUserService::resolve($this);
        // dd($this->all());
        $profile->fill($this->only('name', 'description'));
        $profile->pdo_id = $user->id;
        $profile->free_plans = $this->free_plan;
        $profile->default_plans = $this->default_plan;
        $profile->powered_by = true;
        // $profile->logo = ($this->logo) ? $this->logo->getClientOriginalName() : NULL;
        if ($this->hasFile('logo')) {
            $profile->logo = $this->file('logo')->getClientOriginalName();
        } else {
            $profile->logo = ''; // or some default value
        }
        $profile->banner = ($this->banner) ? $this->banner->getClientOriginalName() : 'null';
        $profile->background_image = ($this->background_image) ? $this->background_image->getClientOriginalName() : 'null';
        $profile->banner_url = $this->banner_url ?? NULL;
        $profile->privacy_policy = $this->privacy_policy ?? NULL;
        $profile->terms_conditions = $this->terms_conditions ?? NULL;
        $profile->support_email = $this->support_email ?? NULL;
        $profile->support_phone = $this->support_phone ?? NULL;
        $profile->support_link = $this->support_link ?? NULL;
        /*
                if ($this->global_payment_gateway == true)  {

                    $profile->global_payment_gateway = 1;
                } else {
                    $profile->global_payment_gateway = 0;
                }*/
        //   return   dd($profile);       
        $profile->save();

        if ($this->payment_gateway == true) {

            $paymentGateway = new PdoPaymentGateway();
            $paymentGateway->pdo_id = $user->id ?? NULL;
            $paymentGateway->zone_id = $profile->id ?? NULL;
            $paymentGateway->secret = $this->secret ?? NULL;
            $paymentGateway->key = $this->key ?? NULL;
            $paymentGateway->is_enable = 1;
            $paymentGateway->providers = 'razorpay';
            $paymentGateway->save();
        }


        $LogoFolder = $folder . $profile->id . '/Logo/';
        $BannerFolder = $folder . $profile->id . '/Banner/';
        $BackgroundImageFolder = $folder . $profile->id . '/BackgroundImage/';

        // if ($this->logo) {
        //     $PROFILE_File = $this->logo;
        //     $PROFILE_FileName = 'LOGO-' . $PROFILE_File->getClientOriginalName();
        //     $PROFILE_File->move($LogoFolder, $PROFILE_FileName);
        // }

        if ($this->hasFile('logo')) {
            if (!file_exists($LogoFolder)) {
                mkdir($LogoFolder, 0777, true);
            }

            $file = $this->file('logo');
            $file->move($LogoFolder, 'LOGO-' . $file->getClientOriginalName());
        }
        
        if ($this->banner) {
            $PROFILE_File = $this->banner;
            $PROFILE_FileName = 'BANNER-' . $PROFILE_File->getClientOriginalName();
            $PROFILE_File->move($BannerFolder, $PROFILE_FileName);
        }

        if ($this->background_image) {
            $PROFILE_File = $this->background_image;
            $PROFILE_FileName = 'BACKGROUNDIMAGE-' . $PROFILE_File->getClientOriginalName();
            $PROFILE_File->move($BackgroundImageFolder, $PROFILE_FileName);
        }

        $urls = $this->get('url', []);

        if (!empty($urls[0])) {
            $whitelistedUrls = json_encode(['urls' => $urls]);
            $profile->whitelisted_urls = $whitelistedUrls;
            $profile->save();
        }

        $locations = Location::whereIn('id', $this->get('locations', []))->get();
        if (count($locations) > 0) {
            $locations->toQuery()->update(['profile_id' => $profile->id]);
        }

        $zoneLocations = Location::where('profile_id', $profile->id)->get();

        $routers = [];
        if (count($zoneLocations) > 0) {
            foreach ($zoneLocations as $zoneLocation) {
                $routersForLocation = Router::where('location_id', $zoneLocation->id)->get();

                if (count($routersForLocation) > 0) {
                    foreach ($routersForLocation as $router) {
                        $wifiRouter = Router::where('location_id', $router->id)->first();
                        if ($wifiRouter) {
                            $wifiRouter->last_configuration_version = $wifiRouter->configurationVersion;
                            $wifiRouter->last_updated_at = $wifiRouter->updated_at;
                            $wifiRouter->increment('configurationVersion');
                            $wifiRouter->save();
                        }
                    }
                    $routers = array_merge($routers, $routersForLocation->toArray());
                }
            }
        }

        $plans = InternetPlan::whereIn('id', $this->get('plans', []))->get();
        $zoneInternetPlanId = ZoneInternetPlan::where('branding_profile_id', $profile->id);
        if (count($zoneInternetPlanId->get()) > 0) {
            $zoneInternetPlanId->delete();
        }
        if ($plans) {
            foreach ($plans as $plan) {
                $zoneInternetPlan = new ZoneInternetPlan();
                $zoneInternetPlan->branding_profile_id = $profile->id;
                $zoneInternetPlan->internet_plan_id = $plan->id;
                $zoneInternetPlan->save();
            }
        }

        DB::commit();

        return $profile;
    }
 
    public function update($id)
    {
        DB::beginTransaction();

        // check folder exists
        $folder = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/branding_profiles/';
        // $user = Auth::user();
        // if ($user->parent_id) {
        //     $parent = User::where('id', $user->parent_id)->first();
        //     $user = $parent;
        // }
        $user = CentralUserService::resolve($this);

        $profile = BrandingProfile::find($this->id);
        $profile->fill($this->only('name', 'description'));
        $profile->pdo_id = $user->id;
        $profile->free_plans = $this->free_plan ?? false;
        $profile->default_plans = $this->default_plan ?? false;
        $profile->powered_by = true;
        $profile->banner_url = $this->banner_url ?? NULL;
        $profile->privacy_policy = $this->privacy_policy ?? NULL;
        $profile->terms_conditions = $this->terms_conditions ?? NULL;
        $profile->support_email = $this->support_email ?? NULL;
        $profile->support_phone = $this->support_phone ?? NULL;
        $profile->support_link = $this->support_link ?? NULL;

        /* if ($this->global_payment_gateway == true)  {

             $profile->global_payment_gateway = 1;
         } else {
             $profile->global_payment_gateway = 0;
         }*/

        $profile->save();

        $profile->logo = ($this->logo) ? $this->logo->getClientOriginalName() : $profile->logo;
        $profile->banner = ($this->banner) ? $this->banner->getClientOriginalName() : ($profile->banner != "" || $profile->banner != null ? $profile->banner : 'null');
        $profile->background_image = ($this->background_image) ? $this->background_image->getClientOriginalName() : ($profile->background_image != "" || $profile->background_image != null ? $profile->background_image : 'null');
        $profile->save();

        $paymentGateway = PdoPaymentGateway::where('zone_id', $this->id)->first();

        if(isset($paymentGateway)){
            $oldKey = $paymentGateway->key;
            $oldSecret = $paymentGateway->secret;
            $oldIsEnabled = $paymentGateway->is_enable;

            if ($oldKey !== $this->key || $oldSecret !== $this->secret || $oldIsEnabled !== $this->is_enable) {
                $paymentGateway->key = $oldKey !== $this->key ? $this->key : $oldKey;
                $paymentGateway->secret = $oldSecret !== $this->secret ? $this->secret : $oldSecret;
                $paymentGateway->is_enable = $oldIsEnabled !== $this->is_enable ? $this->is_enable : $oldIsEnabled;

                $paymentGateway->save();
            }
        } else if (isset($this->key) && isset($this->secret)) {

            $paymentGateway = new PdoPaymentGateway();
            $paymentGateway->pdo_id = $user->id;
            $paymentGateway->zone_id = $this->id;
            $paymentGateway->providers = 'razorpay';
            $paymentGateway->key = $this->key;
            $paymentGateway->secret = $this->secret;
            $paymentGateway->is_enable = true;

            $paymentGateway->save();
        }

        /*$paymentGateway = PdoPaymentGateway::where('zone_id', $this->id)->first();
        if ($this->payment_gateway == true)  {

            if ($paymentGateway != null) {
                $paymentGateway->pdo_id = $user->id;
                $paymentGateway->zone_id = $this->id;
                $paymentGateway->secret = $this->secret;
                $paymentGateway->key = $this->key;
                $paymentGateway->is_enable = 1;
                $paymentGateway->providers = 'razorpay';
                $paymentGateway->save();
            } else {
                $paymentGateway = new PdoPaymentGateway();
                $paymentGateway->pdo_id = $user->id;
                $paymentGateway->zone_id = $this->id;
                $paymentGateway->secret = $this->secret;
                $paymentGateway->key = $this->key;
                $paymentGateway->is_enable = 0;
                $paymentGateway->providers = 'razorpay';
                $paymentGateway->save();
            }

        } else {
            $paymentGateway->is_enable = 0;
            $paymentGateway->save();
        }*/

        $urls = $this->get('url', []);

        if ($profile->whitelisted_urls !== NULL) {
            $profile->whitelisted_urls = null;
            $profile->save();
        }

        if (!empty($urls[0])) {
            $whitelistedUrls = json_encode(['urls' => $urls]);
            $profile->whitelisted_urls = $whitelistedUrls;
            $profile->save();
        }

        $oldLocations = Location::where('profile_id', $profile->id)->get();
        $newLocationIds = $this->get('locations', []);
        $newLocationIds = array_map('intval', $newLocationIds);
        $oldLocationIds = $oldLocations->pluck('id')->toArray();

        /* Log::info('Old Location Ids: ' . $oldLocationIds);
         Log::info('New Location Ids: ' . $newLocationIds);*/

        $addedLocationIds = array_diff($newLocationIds, $oldLocationIds);
        $removedLocationIds = array_diff($oldLocationIds, $newLocationIds);
        //        Log::info("Locations from Frontend: " . $this->get('locations', []));

        $routers = [];
        if (count($removedLocationIds) > 0) {
            foreach ($removedLocationIds as $removedLocationId) {
                $routersForLocation = Router::where('location_id', $removedLocationId)->get();

                if (count($routersForLocation) > 0) {
                    foreach ($routersForLocation as $router) {
                        $wifiRouter = Router::where('location_id', $router->id)->first();
                        if ($wifiRouter) {
                            $wifiRouter->last_configuration_version = $wifiRouter->configurationVersion;
                            $wifiRouter->last_updated_at = $wifiRouter->updated_at;
                            $wifiRouter->increment('configurationVersion');
                            $wifiRouter->save();
                        }
                    }
                    $routers = array_merge($routers, $routersForLocation->toArray());
                }

                Location::where('id', $removedLocationId)->update(['profile_id' => NULL]);
            }
        }

        /*if ($this->id) {
            $locations = Location::where('profile_id', $this->id)->get();
                if(count($locations) > 0) {
                    $locations->toQuery()->update(['profile_id' => NULL]);
                }
        }*/

        $locations = Location::whereIn('id', $addedLocationIds)->get();
        if (count($locations) > 0) {
            $locations->toQuery()->update(['profile_id' => $profile->id]);
        }

        $zoneLocations = Location::where('profile_id', $profile->id)->get();

        $routers = [];
        if (count($zoneLocations) > 0) {
            foreach ($zoneLocations as $zoneLocation) {
                $routersForLocation = Router::where('location_id', $zoneLocation->id)->get();

                if (count($routersForLocation) > 0) {
                    foreach ($routersForLocation as $router) {
                        $wifiRouter = Router::where('location_id', $router->id)->first();
                        if ($wifiRouter) {
                            $wifiRouter->last_configuration_version = $wifiRouter->configurationVersion;
                            $wifiRouter->last_updated_at = $wifiRouter->updated_at;
                            $wifiRouter->increment('configurationVersion');
                            $wifiRouter->save();
                        }
                    }
                    $routers = array_merge($routers, $routersForLocation->toArray());
                }
            }
        }

        $plans = InternetPlan::whereIn('id', $this->get('plans', []))->get();
        $zoneInternetPlanId = ZoneInternetPlan::where('branding_profile_id', $this->id);
        if (count($zoneInternetPlanId->get()) > 0) {
            $zoneInternetPlanId->delete();
        }
        if ($plans) {
            foreach ($plans as $plan) {
                $zoneInternetPlan = new ZoneInternetPlan();
                $zoneInternetPlan->branding_profile_id = $id;
                $zoneInternetPlan->internet_plan_id = $plan->id;
                $zoneInternetPlan->save();
            }
        }

        // file upload using profile id
        $LogoFolder = $folder . $profile->id . '/Logo/';
        $BannerFolder = $folder . $profile->id . '/Banner/';
        $BackgroundImageFolder = $folder . $profile->id . '/BackgroundImage/';

        if (!file_exists($LogoFolder)) {
            mkdir($LogoFolder, 0777, true);
        }
        if (!file_exists($BannerFolder)) {
            mkdir($BannerFolder, 0777, true);
        }
        if (!file_exists($BackgroundImageFolder)) {
            mkdir($BackgroundImageFolder, 0777, true);
        }

        $scanLOGO = scandir($LogoFolder);
        $scanBANNER = scandir($BannerFolder);
        $scanBACKGROUDIMAGE = scandir($BackgroundImageFolder);

        if ($this->logo) {
            foreach ($scanLOGO as $file) {
                if (!is_dir($folder . '/' . $file)) {
                    if (str_contains($file, 'LOGO-')) {
                        unlink($LogoFolder . "/" . $file);
                    }
                }
            }
            $LOGO_File = $this->logo;
            $LOGO_FileName = 'LOGO-' . $LOGO_File->getClientOriginalName();
            $LOGO_File->move($LogoFolder, $LOGO_FileName);
        }
        if ($this->banner) {
            foreach ($scanBANNER as $file) {
                if (!is_dir($folder . '/' . $file)) {
                    if (str_contains($file, 'BANNER-')) {
                        unlink($BannerFolder . "/" . $file);
                    }
                }
            }
            $BANNER_File = $this->banner;
            $BANNER_FileName = 'BANNER-' . $BANNER_File->getClientOriginalName();
            $BANNER_File->move($BannerFolder, $BANNER_FileName);
        }
        if ($this->background_image) {
            foreach ($scanBACKGROUDIMAGE as $file) {
                if (!is_dir($folder . '/' . $file)) {
                    if (str_contains($file, 'BACKGROUNDIMAGE-')) {
                        unlink($BackgroundImageFolder . "/" . $file);
                    }
                }
            }
            $BACKGROUNDIMAGE_File = $this->background_image;
            $BACKGROUNDIMAGE_FileName = 'BACKGROUNDIMAGE-' . $BACKGROUNDIMAGE_File->getClientOriginalName();
            $BACKGROUNDIMAGE_File->move($BackgroundImageFolder, $BACKGROUNDIMAGE_FileName);
        }

        DB::commit();

        return $profile;

    }

}
