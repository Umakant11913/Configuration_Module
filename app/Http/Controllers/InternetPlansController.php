<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInternetPlanForm;
use App\Models\BrandingProfile;
use App\Models\InternetPlan;
use App\Models\InternetPlanVouchers;
use App\Models\Location;
use App\Models\PaymentExtensions;
use App\Models\PdoPaymentGateway;
use App\Models\Router;
use App\Models\WiFiOrders;
use App\Models\WiFiUser;
use App\Models\User;
use App\Models\InternetPlans;
use App\Models\NetworkSettings;
use App\Models\ZoneInternetPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class InternetPlansController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['list', 'show', 'profilePlanindex', 'profilePlanStore', 'profilePlanUpdate']]);
    }

    public function index()
    {
        if (Auth::user()->isAdmin()) {
            return InternetPlan::query()->where('added_by', NULL)->get();

        }
        if (Auth::user()->isPDO() || Auth::user()->isDistributor()) {
            return InternetPlan::query()->where('added_by', NULL)->where('suspended', NULL)->get();
        }
    }

    public function store(CreateInternetPlanForm $form)
    {
        return $form->save();
    }

    public function profilePlanindex(Request $request, $id = null)
    {
        $showAllPlans = $request->allPlans;
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $globalPaymentgateway = false;

        $paymentGateway = PdoPaymentGateway::where('pdo_id', $user->id)->where('key', '!=', null)->where('secret', '!=', null)->first();
        if ($paymentGateway && $showAllPlans == false) {
            $allPlans = InternetPlan::query()->with('zoneInternetPlan')->where('added_by', $user->id)->get();
        } else if ($showAllPlans == false) {
            $allPlans = InternetPlan::query()->with('zoneInternetPlan')->where('added_by', $user->id)->where('price', '=', '0.00')->get();
        }

        if ($showAllPlans) {
            $allPlans = InternetPlan::query()->with('zoneInternetPlan')->where('added_by', $user->id)->get();

            if (isset($paymentGateway)) {
                $globalPaymentgateway = true;
            }

            $finalPlans = [];
            foreach ($allPlans as $plan) {
                $tempPlan = $plan;
                if (in_array($id, $tempPlan->zoneInternetPlan->pluck('branding_profile_id')->toArray())) {
                    $tempPlan->selectedPlan = true;
                } else {
                    $tempPlan->selectedPlan = false;
                }
                $finalPlans[] = $tempPlan;
            }
            return compact(['finalPlans', 'globalPaymentgateway']);

        }

        $finalPlans = [];
        foreach ($allPlans as $plan) {
            $tempPlan = $plan;
            if (in_array($id, $tempPlan->zoneInternetPlan->pluck('branding_profile_id')->toArray())) {
                $tempPlan->selectedPlan = true;
            } else {
                $tempPlan->selectedPlan = false;
            }
            $finalPlans[] = $tempPlan;
        }
        return $finalPlans;
    }

    public function profilePlanStore(CreateInternetPlanForm $form)
    {
        return $form->saveInternetPlan();
    }

    public function profilePlanUpdate($id, CreateInternetPlanForm $form)
    {
        return $form->updateInternetPlan($id);
    }

    public function show(InternetPlan $internet_plan)
    {
        return $internet_plan;
    }

    public function oldindex($id)
    {
        $internetPlan = InternetPlan::where('id', $id)->get();
        if ($internetPlan) {
            return response()->json([
                'status' => true,
                'message' => 'Plan Details retrieved',
                'internetPlan' => $internetPlan
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Plan does not exists',
            ], 401);
        }
    }

    public function list(Request $request)
    {
        //Log::info($request);
        $locationId = $request->location;
        $ownerId = '';
        if (isset($locationId) && $locationId != '0') {
            $location = Location::where('id', $locationId)->first();
            $ownerId = $location->owner_id;
        }
        // PM-Wani Plans
        $internetPlan = InternetPlan::where('added_by', NULL)->whereNull('suspended')->orderBy('price', 'asc')->get();
        $freePlan = NetworkSettings::select('free_download')->where('id', 1)->first();
        $free_access_available = 1;
        $total_free_download = 0;
        $login_redirect_url = '';
        $error = 'No';
        $today = new \DateTime('today midnight');
        $max_available_download = 0;
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $extension_detail = PaymentExtensions::where('user_id', $user->id)->first();

        if ($extension_detail) {
            if (date("Y-m-d", strtotime($extension_detail->extension_date)) == date("Y-m-d")) {
                $free_access_available = 0;
            }
        }

        if ($internetPlan) {

            $addOnData = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $user->phone )->first();

            if ($addOnData) {
                $add_on_data = true;
            } else {
                $add_on_data = false;
            }

            if ($request->input('profileID') !== null) {
                $profileInternetPlan = '';
                $profileFreePlan = BrandingProfile::where('id', $request->profileID)->first();
                 //Log::info($profileFreePlan);

                if ($profileFreePlan->free_plans == true && $profileFreePlan->default_plans == false) {
                    $profileInternetPlan = InternetPlan::whereHas('zoneInternetPlan', function ($query) use ($request) {
                        $query->where('branding_profile_id', $request->profileID);
                    })->where(function ($query) use ($ownerId) {
                        $query->where('plan_type', 'free')
                            ->orWhere('plan_type', 'paid');
                    })->where('added_by', $ownerId)
                        ->whereNull('suspended')
                        ->orderBy('price', 'asc')
                        ->get();
//                    $profileInternetPlan = InternetPlan::whereHas('zoneInternetPlan', function ($query) use ($request) {
//                        $query->where('branding_profile_id', $request->profileID);
//                    })->where('plan_type', 'free')->orWhere('plan_type', 'paid')->where('added_by', $ownerId)->whereNull('suspended')->get();
                }


                if ($profileFreePlan->default_plans == true && $profileFreePlan->free_plans == false) {
                    $profileInternetPlan = InternetPlan::where('plan_type', 'default')->orWhere('plan_type', 'null')->whereNull('suspended')->orderBy('price', 'asc')->get();
                }


                if ($profileFreePlan->free_plans == true && $profileFreePlan->default_plans == true) {
//                    $profileInternetPlan = InternetPlan::whereHas('zoneInternetPlan', function ($query) use ($request) {
//                        $query->where('branding_profile_id', $request->profileID);
//                    })->where('plan_type', 'free')->orWhere('plan_type', 'paid')->where('added_by', $ownerId)->whereNull('suspended')->get();
                    $profileInternetPlan = InternetPlan::whereHas('zoneInternetPlan', function ($query) use ($request) {
                        $query->where('branding_profile_id', $request->profileID);
                    })->where(function ($query) use ($ownerId) {
                        $query->where('plan_type', 'free')
                            ->orWhere('plan_type', 'paid');
                    })->where('added_by', $ownerId)
                        ->whereNull('suspended')
                        ->orderBy('price', 'asc')
                        ->get();

                    $default_plans = InternetPlan::where('plan_type', 'default')->orWhere('plan_type', 'null')->whereNull('suspended')->orderBy('price', 'asc')->get();

                    $profileInternetPlan = $profileInternetPlan->merge($default_plans);

                }


                if ($profileFreePlan->free_plans == false && $profileFreePlan->default_plans == false) {
                    $profileInternetPlan = null;
                }

                $addOnData = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $user->phone )->first();

                if ($addOnData) {
                    $add_on_data = true;
                } else {
                    $add_on_data = false;
                }

                return response()->json([
                    'status' => true,
                    'message' => 'All Plan retrived',
                    'internetPlan' => $profileInternetPlan,
                    'add_on_data' =>$add_on_data,
                    'free_plan' => $freePlan->free_download,
                    'free_access_available' => $free_access_available,
                    'total_free_download' => $total_free_download,
                    'max_available_download' => $max_available_download,
                    'request' => $request->all(),
                    'free_login_url' => $login_redirect_url,
                    'error' => $error
                ], 201);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'All Plan retrived',
                    'internetPlan' => $internetPlan,
                    'add_on_data' =>$add_on_data,
                    'free_plan' => $freePlan->free_download,
                    'free_access_available' => $free_access_available,
                    'total_free_download' => $total_free_download,
                    'max_available_download' => $max_available_download,
                    'request' => $request->all(),
                    'free_login_url' => $login_redirect_url,
                    'error' => $error
                ], 201);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No plans exists',
            ], 201);
        }
    }

    public function listNew(Request $request)
    {
        $internetPlan = InternetPlan::where('added_by', NULL)->where('suspended', null)->get();
        $freePlan = NetworkSettings::select('free_download')->where('id', 1)->first();
        $free_access_available = 1;
        $total_free_download = 0;
        $login_redirect_url = '';
        $error = 'No';
        $today = new \DateTime('today midnight');
        $max_available_download = 0;
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $extension_detail = PaymentExtensions::where('user_id', $user->id)->first();

        if ($extension_detail) {
            if (date("Y-m-d", strtotime($extension_detail->extension_date)) == date("Y-m-d")) {
                $free_access_available = 0;
            }
        }
        foreach ($internetPlan as $plan) {

            $location = Location::where('id', $request->location)->first();
            $zoneId = $location->profile_id ?? '';

            if ($zoneId) {
                $zoneInternetPlanId = ZoneInternetPlan::where('branding_profile_id', $zoneId)->first();
                $voucherShowStatus = InternetPlanVouchers::where('plan_id', $zoneInternetPlanId->internet_plan_id)->get();

                if (!empty($voucherShowStatus)) {
                    $plan['voucherStatus'] = true;
                } else {
                    $plan['voucherStatus'] = false;
                }
            }
        }

        if ($internetPlan) {
            if ($request->input('profileID') !== null) {
                $profileInternetPlan = '';
                $profileFreePlan = BrandingProfile::where('id', $request->profileID)->first();


                if ($profileFreePlan->free_plans == true && $profileFreePlan->default_plans == false) {
                    $profileInternetPlan = InternetPlan::whereHas('zoneInternetPlan', function ($query) use ($request) {
                        $query->where('branding_profile_id', $request->profileID);
                    })->where('plan_type', 'free')->orWhere('plan_type', 'paid')->get();
                }


                if ($profileFreePlan->default_plans == true && $profileFreePlan->free_plans == false) {
                    $profileInternetPlan = InternetPlan::where('plan_type', 'default')->orWhere('plan_type', 'null')->get();
                }
                if ($profileFreePlan->free_plans == true && $profileFreePlan->default_plans == true) {
                    $profileInternetPlan = InternetPlan::whereHas('zoneInternetPlan', function ($query) use ($request) {
                        $query->where('branding_profile_id', $request->profileID);
                    })->where('plan_type', 'free')->get();

                    $default_plans = InternetPlan::where('plan_type', 'default')->orWhere('plan_type', 'null')->get();

                    $profileInternetPlan = $profileInternetPlan->merge($default_plans);

                }
                if ($profileFreePlan->free_plans == false && $profileFreePlan->default_plans == false) {
                    $profileInternetPlan = null;
                }

                return response()->json([
                    'status' => true,
                    'message' => 'All Plan retrived',
                    'internetPlan' => $profileInternetPlan,
                    'free_plan' => $freePlan->free_download,
                    'free_access_available' => $free_access_available,
                    'total_free_download' => $total_free_download,
                    'max_available_download' => $max_available_download,
                    'request' => $request->all(),
                    'free_login_url' => $login_redirect_url,
                    'error' => $error,
//                    'voucherButton' => $voucherButton,

                ], 201);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'All Plan retrived',
                    'internetPlan' => $internetPlan,
                    'free_plan' => $freePlan->free_download,
                    'free_access_available' => $free_access_available,
                    'total_free_download' => $total_free_download,
                    'max_available_download' => $max_available_download,
                    'request' => $request->all(),
                    'free_login_url' => $login_redirect_url,
                    'error' => $error
                ], 201);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No plans exists',
//                'voucherButton' => $voucherButton,
            ], 201);
        }
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:1,32',
            'description' => 'string',
            'price' => 'required|string',
            'bandwidth' => 'required|integer',
            'data_limit' => 'required|integer',
            'validity' => 'required|integer',
            'status' => 'required|integer|between:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->toJson(), 400);
        }

        $internetPlan = InternetPlan::create(
            $validator->validated()
        );

        return response()->json([
            'message' => 'Internet Plan created',
            'internetPlan' => $internetPlan
        ], 201);
    }

    public function suspendPlan(Request $request)
    {

        if (Auth::user()->isAdmin()) {
            $internetPlan = InternetPlan::where('id', $request->id)->first();
            if ($internetPlan) {
                if ($request->plantype === "suspend") {
                    $internetPlan->suspended = 1;
                    $internetPlan->save();

                    return response()->json([
                        'suspended' => $request->id,
                        'message' => 'The Internet Plan has been suspended successfully!'
                    ], 200);
                } else if ($request->plantype === "activate") {
                    $internetPlan->suspended = null;
                    $internetPlan->save();

                    return response()->json([
                        'suspended' => $request->id,
                        'message' => 'The Internet Plan has been activated successfully!'
                    ], 200);
                } else {
                    return response()->json([
                        'suspended' => $request->id,
                        'message' => 'Please try again!'
                    ], 200);
                }
            }
        }

        if (Auth::user()->isPDO() || Auth::user()->isDistributor()) {
            return response()->json([
                'suspended' => $request,
                'message' => 'you are not allowed to suspend any Internet Plan'
            ], 200);
        }
    }

}
