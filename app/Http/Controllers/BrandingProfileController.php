<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBrandingProfileForm;
use App\Models\BrandingProfile;
use App\Models\InternetPlan;
use App\Models\Location;
use App\Models\User;
use App\Models\ZoneInternetPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\CentralUserService;

class BrandingProfileController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api', ['except' => ['getOne', 'show']]);
    // }

    public function index(Request $request)
    {
        // $user = Auth::user();
        // if ($user->parent_id) {
        //     $parent = User::where('id', $user->parent_id)->first();
        //     $user = $parent;
        // }
        $user = CentralUserService::resolve($request);
        return BrandingProfile::with(['locations:id,name,profile_id'])->where('pdo_id', $user->id)->orderBy('created_at', 'desc')->get();
    }

    public function store(CreateBrandingProfileForm $form)
    {
        // return "ROOT: " . dd($_SERVER['DOCUMENT_ROOT']);
        $profile = $form->save();
        return response()->json([
            'status' => true,
            'data' => $profile
        ]);
    }

    public function getOne(Request $request)
    {
        $locationId = $request->get('location_id');

        $location = Location::where('id', $locationId)->first();

        if ($locationId) {
            $profileData = BrandingProfile::where('id', $location->profile_id)->first();

            if( $profileData){
                return $profileData;
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Profile Data Does not exist'
            ]);
        }
    }

    public function show(BrandingProfile $id)
    {
        $brandingProfiles = BrandingProfile::with(['pdo', 'locations', 'zoneInternetPlan' ,'pdoPaymentGateway'])
            ->where('id', $id->id)
            ->first();

        $internetPlans = collect();

        if ($brandingProfiles->zoneInternetPlan->isNotEmpty()) {
            // Rows exist in zoneInternetPlans table
            foreach ($brandingProfiles->zoneInternetPlan as $zoneInternetPlan) {
                $internetPlan = InternetPlan::find($zoneInternetPlan->internet_plan_id);
                if ($internetPlan) {
                    $internetPlans->push($internetPlan);
                }
            }
        } else {
            $internetPlans = '';
        }

        return [
            'brandingProfiles' => $brandingProfiles,
            'internetPlans' => $internetPlans,
        ];
    }

    public function update($id, CreateBrandingProfileForm $form)
    {
        return $form->update($id);
    }

    public function saveZonePlans($id, Request $request)
    {
        $plans = InternetPlan::whereIn('id', $request->get('plans', []))->get();

        $zoneInternetPlanId = ZoneInternetPlan::where('branding_profile_id', $id);
        if(count($zoneInternetPlanId->get()) > 0) {
            $zoneInternetPlanId->delete();
        }

        foreach ($plans as $plan){
            $zoneInternetPlan = new ZoneInternetPlan();

            $zoneInternetPlan->branding_profile_id = $id;
            $zoneInternetPlan->internet_plan_id = $plan->id;
            $zoneInternetPlan->save();
        }

        return response()->json(['status' => 'true', 'message' => 'Updated']);
    }

    public function delete($id)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $pdo = $user->id;
      $brandingProfile = BrandingProfile::where('id' ,$id)->where('pdo_id',$pdo)->first();
      if($brandingProfile) {
          $locations = Location::where('profile_id',$brandingProfile->id)->get();
          if ($locations) {
              foreach ($locations as $profileId) {
                  $profileId->profile_id = NULL;
                  $profileId->save();
              }
          }
          $zoneInternet = ZoneInternetPlan::where('branding_profile_id',$brandingProfile->id)->get();
          foreach ($zoneInternet as $zoneInternetPlan) {
              $zoneInternetPlan->delete();
          }
          $brandingProfile->delete();
              return response()->json([
                  'branding_profile' =>$id,
                  'message' =>'Branding Profile Delete Successfully'
              ],200);
          }
            return response()->json([
                'branding_profile' =>$id,
                'message' =>'Branding Profile Does not Exist anymore!'
            ],200);
    }

    public function suspendPlan(Request $request) {

        if(Auth::user()->isAdmin()){
            $internetPlan = InternetPlan::where('id', $request->id)->first();

            if ($internetPlan) {

                $internetPlan->suspended = 1;
                $internetPlan->save();
            }

            return response()->json([
                'suspended' =>$request->id,
                'message' =>'The Internet Plan has been suspended successfully!'
            ],200);
        }

        if(Auth::user()->isPDO()){
            $internetPlan = InternetPlan::where('id', $request->id)->first();

            if ($internetPlan) {
                if ($request->plantype === "suspend") {
                    $internetPlan->suspended = 1;
                    $internetPlan->save();

                    $zoneInternetPlan = ZoneInternetPlan::where('internet_plan_id',$request->id)->get();
                    if ($zoneInternetPlan) {
                        foreach ($zoneInternetPlan as $zoneInternetPlans) {
                            $zoneInternetPlans->delete();
                        }
                    }

                    return response()->json([
                        'suspended' =>$request->id,
                        'message' =>'The Internet Plan has been suspended successfully!'
                    ],200);
                }

                if ($request->plantype === "activate") {
                    $internetPlan->suspended = null;
                    $internetPlan->save();

                    return response()->json([
                        'suspended' =>$request->id,
                        'message' =>'The Internet Plan has been activated successfully!'
                    ],200);
                }
            }

            return response()->json([
                'suspended' => $request,
                'message' =>'No Internet Plan found!'
            ],200);
        }
    }

    public function hotspotprofiles()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        return BrandingProfile::select("id","name")->where('pdo_id', $user->id)->orderBy('created_at', 'desc')->get();
    }

}
