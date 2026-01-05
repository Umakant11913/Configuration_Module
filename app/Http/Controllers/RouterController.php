<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRouterForm;
use App\Models\CreditsHistoy;
use App\Models\Location;
use App\Models\ModelFirmwares;
use App\Models\Models;
use App\Models\PdoaPlan;
use App\Models\PdoCredits;
use App\Models\PdoSmsQuota;
use App\Models\Router;
use App\Models\Distributor;
use App\Models\User;
use App\Models\WiFiStatus;
use com\zoho\crm\api\util\Model;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Mockery\Matcher\Not;

class RouterController extends Controller
{
    
    public function index(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $user = User::find($user->parent_id);
        }

        $apFilter = $request->get('apFilter');
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');
        $columnIndex = $columnIndex_arr[0]['column'];
        $columnName = $columnName_arr[$columnIndex]['data'];
        $columnSortOrder = $order_arr[0]['dir'];
        $searchValue = $search_arr['value'];

        $baseQuery = Router::leftJoin('users', 'routers.owner_id', '=', 'users.id')
            ->leftJoin('locations', 'routers.location_id', '=', 'locations.id')
            ->leftJoin('models', 'routers.model_id', '=', 'models.id')
            ->select(
                'routers.*',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as owner_name"),
                'locations.name as location_name',
                'models.name as model_name',
                'locations.owner_id as location_owner_id'
            )
            ->where(function ($q) use ($searchValue) {
                $q->where('users.first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('locations.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.mac_address', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.eth1', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.wireless1', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.wireless2', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.serial_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.status', 'like', '%' . $searchValue . '%')
                    ->orWhere('models.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.latest_tar_version', 'like', '%' . $searchValue . '%');

                    if (strtolower($searchValue) == 'online') {
                        $q->orWhere('routers.lastOnline', '>=', now()->subMinutes(3));
                        
                    } elseif (strtolower($searchValue) == 'offline') {
                        $q->orWhere(function ($q2) {
                            $q2->whereNull('routers.lastOnline')->orWhere('routers.lastOnline', '<', now()->subMinutes(3));
                        });
                    }
            });
        $baseQuery->where("inventory_type", 'ap');
        // Apply assignment filter
        if ($request->assignment == 'unassigned') {
            $baseQuery->unassigned($request->get('with_location'));
        }

        // Role-based filters
        if ($user->isDistributor()) {
            $distributor = Distributor::where('owner_id', $user->id)->first();
            $baseQuery->where('distributor_id', $distributor->id);

            $baseQuery->where("inventory_type", 'ap'); 
        }

        if ($user->isPDO()) {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('pdo_id', $user->id)
                    ->orWhere('users.id', $user->id)
                    ->orWhere('routers.owner_id', $user->id)
                    ->orWhereHas('location', function ($l) use ($user) {
                        $l->where('locations.owner_id', $user->id);
                    });
            });

            if ($apFilter === 'offline') {
                $baseQuery->where(function ($q) {
                    $q->whereNull('routers.lastOnline')->orWhere('lastOnline', '<', now()->subMinutes(3));
                });
            } elseif ($apFilter === 'online') {
                $baseQuery->where('lastOnline', '>=', now()->subMinutes(3));
            }elseif ($apFilter === 'retired') {
                $baseQuery->where('retired', 1);
            }

            $baseQuery->where("inventory_type", 'ap'); 
        }

        if ($user->isAdmin()) {
            if ($apFilter === 'spare-aps') {
                $baseQuery->whereNull('routers.location_id')->whereNull('routers.owner_id');
            } elseif ($apFilter === 'assigned-aps') {
                $baseQuery->where(function ($q) {
                    $q->whereNotNull('routers.location_id')->orWhereNotNull('routers.owner_id');
                });
            } elseif ($apFilter === 'offline') {
                $baseQuery->where(function ($q) {
                    $q->whereNull('routers.lastOnline')->orWhere('lastOnline', '<', now()->subMinutes(3));
                });
            } elseif ($apFilter === 'online') {
                $baseQuery->where('lastOnline', '>=', now()->subMinutes(3));
            }elseif ($apFilter === 'retired') {
                $baseQuery->where('retired', 1);
            }
            $baseQuery->where("inventory_type", 'ap');
        }

        // Clone for counts
        $countQuery = clone $baseQuery;
        $totalRecords = $countQuery->count();
        $totalRecordswithFilter = $totalRecords;
        // Fetch paginated data
        $sortableColumns = [
            'device' => '',
            'name' => 'routers.name',
            'mac_address' => 'routers.mac_address',
            'eth1' => 'routers.eth1',
            'wireless1' => 'routers.wireless1',
            'wireless2' => 'routers.wireless2',
            'serial_number' => 'routers.serial_number',
            'status' => 'routers.status',
            'owner' => 'users.first_name',
            'location_name' => 'locations.name',
            'model_name' => 'models.name',
            'batch_no' => 'routers.batch_no',
            'latest_version' => 'routers.latest_tar_version',
        ];
        $orderByColumn = $sortableColumns[$columnName] ?? 'routers.created_at';
        $routers = $baseQuery
            // ->orderBy($columnName ?: 'routers.created_at', $columnSortOrder ?: 'desc')
            ->orderBy($orderByColumn, $columnSortOrder ?: 'desc')
            ->skip($start)
            ->take($rowperpage)
            // dd($routers->toSql());
            ->get();
        // Optionally eager load relationships after fetching
        $routers->load(['owner', 'location', 'distributor', 'model']);

        $data_arr = [];
        foreach ($routers as $router) {
            $status = 'NA';
            if (isset($router->lastOnline)) {
                $status = $router->lastOnline >= Carbon::now()->subMinutes(3) ? 'online' : 'offline';
            }
            $firmwares = ModelFirmwares::where('model_id', $router->model_id)->where('released', 1)->get();
            $data_arr[] = [
                'id' => $router->id,
                'device_img' => null,
                'device_type' => strtoupper($router->inventory_type),
                'name' => $router->name ?? null,
                'model' => $router->model ?? null,
                'location' => $router->location->name ?? null,
                'mac_address' => $router->mac_address,
                'eth1' => $router->eth1,
                'wireless1' => $router->wireless1,
                'wireless2' => $router->wireless2,
                'serial_number' => $router->serial_number,
                'status' => $status,
                'owner' => $router->owner_name,
                'location_id' => $router->location_id ?? null,
                'distributor_id' => $router->distributor_id ?? null,
                'firmwareVersion' => $router->firmwareVersion ?? null,
                'firmwares' => $firmwares,
                'latest_version' => $router->latest_tar_version ?? null,
                'retired' => $router->retired,
                'batch_no' => $router->batch_no ?? null
            ];
        }

        return response()->json([
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        ]);
    }

    public function index_old(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        $apFilter = $request->get('apFilter'); // For Filtering data on select
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page
        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');
        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // For Search value
//        $startDate = $request->get('startDate');
//        $endDate = $request->get('endDate');

        // Total records
        $totalRecords = '';
        $totalRecordswithFilter = '';

        $query = Router::leftJoin('users', 'routers.owner_id', '=', 'users.id')
            ->leftJoin('locations', 'routers.location_id', '=', 'locations.id')
            ->leftJoin('models', 'routers.model_id', '=', 'models.id')
            ->select('routers.*', 'routers.owner_id as owner_id', 'users.first_name as owner_name', 'locations.name as location_name', 'models.name as model_name', 'locations.owner_id as location_owner_id')
            ->where(function ($query) use ($searchValue) {
                $query->where('users.first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('locations.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.mac_address', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.eth1', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.wireless1', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.wireless2', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.serial_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.status', 'like', '%' . $searchValue . '%')
                    ->orWhere('models.name', 'like', '%' . $searchValue . '%');
            })
            ->with(['owner', 'location', 'distributor', 'model']);

        if ($request->assignment == 'unassigned') {
            $query->unassigned($request->get('with_location'));
        }
        //$query->mine();
        $totalRecords = $query->count();

        if ($user->isDistributor()) {

            $getId = Distributor::where('owner_id', Auth::user()->id)->first();
            $query = $query->where('distributor_id', $getId->id);

            $totalRecords = $query->count();
            $totalRecordswithFilter = $query->count();

            $routers = $query->orderBy('routers.created_at', 'desc')->
            take($rowperpage)
                ->skip($start)
                ->get();
        }

        if ($user->isPDO()) {

            $query->with(['model.firmwares' => function ($q) {
                $q->where('released', 1);
            }])->where(function($q) use($user) {
                $q->where(function ($innerQ) use($user) {
                    $innerQ->where('pdo_id', $user->id)
                        ->orWhere('users.id', $user->id);
                })->
                orWhereHas('location', function($l) use($user) {
                    $l->where('locations.owner_id', $user->id);
                });
            })->orWhere('routers.owner_id', $user->id);


            $totalRecords = $query->count();
            $totalRecordswithFilter = $query->count();

            $routers = $query->orderBy('routers.created_at', 'desc')->
            take($rowperpage)
                ->skip($start)
                ->get();

        }

        if ($user->isAdmin()) {
            if ($apFilter && $apFilter == 'spare-aps') {

                $query->where(function ($query) {
                    $query->whereNull('routers.location_id')
                        ->whereNull('routers.owner_id');
                });

            } elseif ($apFilter && $apFilter == 'assigned-aps') {
                $query->where(function ($query) {
                    $query->whereNotNull('routers.location_id')
                        ->orWhereNotNull('routers.owner_id');
                });
            } elseif ($apFilter && $apFilter == 'offline') {
                $query->where(function ($query) {
                    $query->whereNull('routers.lastOnline')
                        ->orWhere('lastOnline', '<', now()->subMinutes(3));
                });
            } elseif ($apFilter && $apFilter == 'online') {

                
                $query->where(function ($query) {
                    $query->orWhere('lastOnline', '>=', now()->subMinutes(3));
                });


            }

            $totalRecords = $query->count();
            $totalRecordswithFilter = $query->count();

            $routers = $query->orderBy('routers.created_at', 'desc')->
            take($rowperpage)
                ->skip($start)
                ->get();
        }

        $data_arr = array();
        foreach ($routers as $router) {
            $id = $router->id;
            $name = $router->name ?? null;
            $model = $router->model ?? null;
            $mac_address = $router->mac_address;
            $location = $router->location->name ?? null;
            $eth1 = $router->eth1;
            $wireless1 = $router->wireless1;
            $wireless2 = $router->wireless2;
            $serial_number = $router->serial_number;
            if (isset($router->lastOnline) && $router->lastOnline->gte(Carbon::now()->subMinutes(3))) {
                $status = 'online';
            } elseif (isset($router->lastOnline) && !$router->lastOnline->gte(Carbon::now()->subMinutes(3))) {
                $status = 'offline';
            } else {
                $status = 'NA';
            }
            $owner = $router->owner_name;
            $location_id = $router->location_id ?? null;
            $distributor_id = $router->distributor_id ?? null;
            $firmwareVersion = $router->firmwareVersion ?? null;
            $firmwares = ModelFirmwares::where('model_id', $router->model_id)->where('released', 1)->get();
            $latest_version = $router->latest_tar_version ?? null;

            $data_arr[] = array(
                'id' => $id,
                'name' => $name,
                'model' => $model,
                'location' => $location,
                'mac_address' => $mac_address,
                'eth1' => $eth1,
                'wireless1' => $wireless1,
                'wireless2' => $wireless2,
                'serial_number' => $serial_number,
                'status' => $status,
                'owner' => $owner,
                'location_id' => $location_id,
                'distributor_id' => $distributor_id,
                'firmwareVersion' => $firmwareVersion,
                'firmwares' => $firmwares ?? null,
                'latest_version' => $latest_version ?? null,
            );
        }

        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        );

        return response()->json($response);
    }

    public function store(CreateRouterForm $form)
    {
        $location_id = $form->location_id;
        $old_location_id = Router::where('id', $form->id)->first();
        if($old_location_id){
            if($form->location_id != $old_location_id->location_id){
              $routers =  Router::where('id', $form->id)->first();
                if ($routers) {
                    $routers->last_configuration_version = $routers->configurationVersion;
                    $routers->last_updated_at = $routers->updated_at;
                    $routers->increment('configurationVersion');
                    $routers->save();
                }
            }
            if ($form->wifi_configuration_profile_id != $old_location_id->wifi_configuration_profile_id) {
                $routers =  Router::where('id', $form->id)->first();
                if ($routers) {
                    $routers->last_configuration_version = $routers->configurationVersion;
                    $routers->last_updated_at = $routers->updated_at;
                    $routers->increment('configurationVersion');
                    $routers->save();
                }
            }
        }

        //dd($form);
        return $form->save();
    }

    public function show(Router $router)
    {
        $user = null;
        $pdoPlan = null;
        $credits = null;
        $showActivate = false;

        if ($router->owner_id !== null) {
            $user = User::where('id', $router->owner_id)->first();
            $pdoPlan = PdoaPlan::where('id', $user->pdo_type)->first();
            $credits = PdoCredits::where('pdo_id', $router->owner_id)->where('expiry_date', today())->first();
            if($credits){
                $usedCredits = $credits->credits - $credits->used_credits;
                if($usedCredits == 0) {
                    $showActivate = true;
                }
            }
        } else {
            $location = Location::where('id', $router->location_id)->first();
            if ($location !== null) {
                $user = $location->owner;
                if ($user !== null) {
                    $pdoPlan = PdoaPlan::where('id', $user->pdo_type)->first();
                    $credits = PdoCredits::where('pdo_id', $user->id)->first();
                }
            }
        }
        $router->pdo_plan = $pdoPlan;
        $router->credits = $credits ?? 0;
        $router->showActivate = $showActivate;

        $router->load('location', 'owner', 'model', 'wifiConfigurationProfile');

        return $router;
        /*$user =User::where('id',$router->owner_id)->first();

        $pdoPlan = PdoaPlan::where('id',$user->pdo_type)->first();
        $router->pdo_plan = $pdoPlan;
        $credits = PdoCredits::where('pdo_id',$router->owner_id)->first();
        $router->credits = $credits ?? 0;
        $router->load('location', 'owner', 'model');
        return $router;*/
    }

    public function destroy(Router $router)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        if (!$user->isAdmin()) {
            abort(403, 'Sorry! You are not authorized to delete');
        }
        $router->delete();
        return $router;
    }

    public function getPdos(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $query = User::where('role', 1);
        if ($user->isDistributor()) {
            $query = $query->where('is_parentId', Auth::user()->id)->get();
        }
        if ($user->isAdmin()) {
            $query = $query->get();
        }

        return $query;
    }

    public function getDistributors(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $query = Distributor::with('owner');
        if ($user->isDistributor()) {
            $query = $query->where('parent_dId', $user->id)->get();
        }
        if ($user->isAdmin()) {
            $query = $query->get();
        }

        return $query;
    }

    public function assignRouter(Request $request, Router $router)
    {
        try {

            $id = $request->id;
            $route = $router->find($id);
            $type = $request->assign_type;

            if($type == 'pdo') {
                $route->update([
                    'pdo_id' => $request->pdo_assign,
                    'owner_id' => $request->pdo_assign,
                    'location_id' => $request->distributor_assign,
                    'distributor_id' => NULL,
                    'distributor_service_type' => NULL,
                    'ap_assignment_time' => Carbon::now()
                ]);
                //$this->assignSmsQuota($request, $route);
            }

            if($type == 'distributor') {
                $route->update([
                    'pdo_id' => NULL,
                    'owner_id' => Distributor::where('id', $request->dist_assign)->first()->owner_id,
                    'distributor_id' => $request->dist_assign,
                    'distributor_service_type' => $request->invest_stock,
                    'ap_assignment_time' => Carbon::now()
                ]);
            }
            $router = Router::with('owner')->get();

            $query = Router::with('owner', 'location');
            if ($request->assignment == 'unassigned') {
                $query->unassigned($request->get('with_location'));
            }
            $query->mine();
            return $query->get();

        } catch(\Exception $e) {
            return response()->json(['status' => 200, 'message' => 'Something went wrong']);
        }
    }

    public function updateFirmware($id)
    {

        $router = Router::where('id', $id)->first();
        $modelFirmwareVersion = Models::where('id', $router->model_id)->first();

        if($router->firmwareVersion === null || $router->firmwareVersion !== $modelFirmwareVersion->firmware_version){
            $updateRouterConfigVersion = Router::where('id', $id)->increment('config_version');
            $updateRouterConfigurationVersion = Router::where('id', $id)->first();
            if ($updateRouterConfigurationVersion) {
                $updateRouterConfigurationVersion->last_configuration_version = $updateRouterConfigurationVersion->configurationVersion;
                $updateRouterConfigurationVersion->last_updated_at = $updateRouterConfigurationVersion->updated_at;
                $updateRouterConfigurationVersion->increment('configurationVersion');
                $updateRouterConfigurationVersion->save();
            }
            $updateRouterFirmwareVersion = Router::where('id', $id)->where('model_id', $router->model_id)->update(['firmwareVersion' => $modelFirmwareVersion->firmware_version]);
            $modelFirmwareDateUpdate = Models::where('id', $router->model_id)->update(['last_firmware_updated_at' => Carbon::now()]);

            return response()->json(['status' => 200, 'message' => 'Firmware has been updated! It will take few minutes to update the Router']);
        } else {
            return response()->json(['status' => 200, 'message' => 'Already Updated to latest firmware version']);
        }
    }



    public function restartResetAP(Request $request, $routerId){
        $router = Router::where('id', $routerId)->first();
        if($router){
            if($request->get('restart') === 'true' && $request->input('reset') === 'false') {
                $router->reboot_required = 1;
                $router->last_configuration_version = $router->configurationVersion;
                $router->last_updated_at = $router->updated_at;
                $router->configurationVersion = $router->configurationVersion + 1;
                $router->save();
                // Prepare the payload for the background job
                $payload = [
                    'devices' => [
                        [
                            'mac' => $router->mac_address,
                            'reboot' => true,
                            'config_version' => $router->configurationVersion,
                        ]
                    ]
                ];             

                return response()->json(['status' => 200, 'message' => 'AP will take some time to reboot. Please wait for some time!', 'payload' => $payload, 'mqtt' => true]);                
            } else if($request->get('reset') === 'true') {
                $router->reset_required = 1;
                $router->last_configuration_version = $router->configurationVersion;
                $router->last_updated_at = $router->updated_at;
                $router->configurationVersion = $router->configurationVersion + 1;
                $router->save();

                return response()->json(['status' => 200, 'message' => 'AP will take some time to reset. Please wait for some time!', 'payload' => $payload, 'mqtt' => true]);
            } else if($request->get('reset_factory') === 'true') {
                $router->reset_factory_required = 1;
                $router->last_configuration_version = $router->configurationVersion;
                $router->last_updated_at = $router->updated_at;
                $router->configurationVersion = $router->configurationVersion + 1;
                $router->save();
                // Prepare the payload for the background job
                $payload = [
                    'devices' => [
                        [
                            'mac' => $router->mac_address,
                            'reset' => true,
                            'config_version' => $router->configurationVersion,
                        ]
                    ]
                ];

                return response()->json(['status' => 200, 'message' => 'AP will take some time to reset factory. Please wait for some time!', 'payload' => $payload, 'mqtt' => true]);
            }
        }

        return response()->json(['status' => 200, 'message' => 'AP not found!']);
    }

    public function activeRouter($id)
    {
        $router = Router::where('id', $id)->first();

        $user = User::where('id', $router->owner_id)->where('role', 1)->first();
        $autoRenew = $user->where('auto_renew_subscription', 1)->first();

        if ($router) {
            if ($router->auto_renewal_date >= today()) {
                $router->is_active = 1;
                //$router->increment('configurationVersion');
                $router->save();

            } else if($router->auto_renewal_date == null){

                $pdoPlans = PdoaPlan::find($user->pdo_type);

                if($pdoPlans->where('grace_period', '!=', null)->orWhere('validity_period', '!=', null) && $autoRenew) {
                    $startOfMonth = Carbon::now()->firstOfMonth()->startOfDay();
                    $endOfMonth = Carbon::now()->lastOfMonth()->endOfDay();
                    $startOfMonthFormatted = $startOfMonth->format('Y-m-d H:i:s');
                    $endOfMonthFormatted = $endOfMonth->format('Y-m-d H:i:s');

                    $pdoSmsQuota = PdoSmsQuota::where('pdo_id', $user->id)
                        ->where('created_at', '>=', $startOfMonthFormatted)
                        ->where('created_at', '<=', $endOfMonthFormatted)
                        ->where('type', null)
                        ->first();

                    $routerHistory = CreditsHistoy::where('pdo_id', $user->id)
                        ->where('created_at', '>=', $startOfMonthFormatted)
                        ->where('created_at', '<=', $endOfMonthFormatted)
                        ->where('type', 'deactivate')
                        ->first();


                    if (!$routerHistory) {
                        if ($pdoSmsQuota) {
                            $pdoSmsQuota->sms_quota += $pdoPlans->sms_quota;
                            $pdoSmsQuota->save();
                        } else {
                            PdoSmsQuota::create(['pdo_id' => $user->id, 'sms_quota' => $pdoPlans->sms_quota]);
                        }
                    }

                    $oldestRecordWithBalance = null;
                    $oldestRecordCreatedAt = null;
                    //find the oldest record whose expiry date IGT today and still have unused credits
                    $credits = PdoCredits::where('pdo_id', $router->owner_id)->where('expiry_date', '>=', today())->get();

                    //get the oldest record
                    if ($credits->isNotEmpty()) {

                        foreach ($credits as $credit) {
                            $balance = $credit->credits - $credit->used_credits;

                            if ($balance > 0 && ($oldestRecordWithBalance == null || $credit->created_at < $oldestRecordCreatedAt)) {
                                $oldestRecordWithBalance = $credit;
                                $oldestRecordCreatedAt = $credit->created_at;
                            }
                        }
                    }

                    $creditHistory = CreditsHistoy::where('router_id', $id)->where('type', 'activate')
                        ->where('created_at', '>=', Carbon::now()->subDays(29))
                        ->where('created_at', '<=', Carbon::now())
                        ->first();

                    //if oldest record found, then update the PDOCredits & Credits History table acc.
                    if ($oldestRecordWithBalance) {

                        if (!$creditHistory) {
                            if ($autoRenew) {
                                $active_date = $router->updated_at;
                                //$auto_renewal_date = Carbon::parse($active_date)->addMonths(1);
                                $auto_renewal_date = Carbon::parse($active_date)->addDays(1);
                                $router->auto_renewal_date = $auto_renewal_date;
                                //$router->increment('configurationVersion');
                                $router->save();
                            }
                            $oldestRecordWithBalance->used_credits = $oldestRecordWithBalance->used_credits + 1;
                            $oldestRecordWithBalance->save();
                            CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $router->id, 'type' => 'activate', 'pdo_credits_id' => $oldestRecordWithBalance->id, 'credit_used' => '1']);

                        } else {
                            CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $router->id, 'type' => 'activate', 'pdo_credits_id' => $oldestRecordWithBalance->id, 'credit_used' => '0']);
                        }
                    } else {
                        return response()->json(['message' => 'Not Enough Credits on PDOs Account'], 400);

                        /*$router->is_active = 1;
                        $router->save();*/
                    }
                } else {
                    $router->is_active = 1;
                    $router->save();

                    return response()->json(['message' => 'WiFi Router activated successfully'], 200);

                    /*$router->is_active = 1;
                    $router->save();*/
                }
            } else {
                if($router->auto_renewal_date != null && $router->is_active == 1) {
                    return response()->json(['message' => 'WiFi Router is already activated!'], 200);
                } else {
                    return response()->json(['message' => 'An error occurred while activating the router.'], 500);
                }
            }
        } else {
            return response()->json(['message' => 'WiFi Router does not exist.'], 404);
        }
    }

    public function deActiveRouter($id)
    {
        try {
            $router = Router::where('id', $id)->first();

            $oldestRecordWithBalance = null;
            $oldestRecordCreatedAt = null;

            if ($router) {
                if ($router->auto_renewal_date >= today()) {
                    $router->is_active = 0;
                    //$router->increment('configurationVersion');
                    $router->save();
                } else {
                    $router->is_active = 0;
                    $router->auto_renewal_date = null;
                    //$router->increment('configurationVersion');
                    $router->save();
                }
                $user = User::where('id', $router->owner_id)->where('role', 1)->first();
                /*$paoPlans = PdoaPlan::find($user->pdo_type);*/
                $credits = PdoCredits::where('pdo_id', $router->owner_id)->where('expiry_date', '>=', today())->get();

                //get the oldest record
                if($credits->isNotEmpty()) {
                    foreach ($credits as $credit) {
                        $balance = $credit->credits - $credit->used_credits;

                        if ($balance > 0 && ($oldestRecordWithBalance == null || $credit->created_at < $oldestRecordCreatedAt)) {
                            $oldestRecordWithBalance = $credit;
                            $oldestRecordCreatedAt = $credit->created_at;
                        }
                    }
                }

                //if oldest record found, Credits History table acc.
                if($oldestRecordWithBalance){
                    CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $router->id, 'type' => 'deactivate', 'pdo_credits_id' => $oldestRecordWithBalance->id]);
                }
                return response()->json(['message' => 'WiFi Router Deactivated successfully!'], 200);
            } else {
                return response()->json(['message' => 'WiFi Router does not exist.'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while activating the router.'], 500);
        }

    }

    public function routerList(Request $request)
    {

        $query = Router::with('owner', 'location', 'distributor', 'model');
        if ($request->assignment == 'unassigned') {
            $query->unassigned($request->get('with_location'));
        }
        if (Auth::user()->isPDO()) {
            $query->where("retired", 0);
        }
        $query->mine();

        if (Auth::user()->isDistributor()) {
            $getId = Distributor::where('owner_id', Auth::user()->id)->first();
            //$query = $query->where('owner_id', $getId->id)->get();
            $query = $query->where('distributor_id', $getId->id)->get();
        }

        if (Auth::user()->isPDO()) {
            //$query = $query->where('owner_id', Auth::user()->id)->get();
            $routers = $query->with(['owner', 'location', 'distributor', 'model.firmwares' => function ($q) {
                $q->orWhere('released', 1);
            }])->where(function ($q) {
                $q->where('pdo_id', Auth::user()->id)->orWhere('owner_id', Auth::user()->id);
            })->orWhereHas('location', function ($l) {
                $l->where('owner_id', Auth::user()->id);
            })
               
               ->get();

            return $routers;
        }
        if (Auth::user()->isAdmin()) {
            $query = $query->get();
        }

        return $query;
    }

    private function assignSmsQuota($request, $route)
    {
        if ($request->pdo_assign != null) {
            $credits = PdoCredits::where('pdo_id', $request->pdo_assign)->first();
            if ($credits) {
                $credits->used_credits = $credits->used_credits !== null ? $credits->used_credits + 1 : 1;
                $credits->save();
            } else {
            }
        } else {
        }
        /* $total_router = Router::where('pdo_id', $request->pdo_assign)->count();
         if ($total_router) {
             $user = User::where('id', $request->pdo_assign)->where('role', 1)->first();
             if ($user) {
                 $pdoPlan = PdoaPlan::where('id', $user->pdo_type)->first();
                 if ($pdoPlan) {
                     $totalSmsCredits = $total_router * $pdoPlan->sms_quota;
                     $pdoSettings = PdoSettings::where('pdo_id', $request->pdo_assign)->first();
                     if (!$pdoSettings) {
                         $pdoSettings = new PdoSettings();
                         $pdoSettings->pdo_id = $request->pdo_assign;
                         $pdoSettings->period_quota = $totalSmsCredits;
                         $pdoSettings->save();

                         event(new PdoSmsQuotaEvent($user, $total_router, $pdoSettings));
                     } else {
                         $pdoSettings->period_quota = $totalSmsCredits;
                         $pdoSettings->save();

                         event(new PdoSmsQuotaEvent($user, $total_router, $pdoSettings));
                     }
                 } else {
                 }
             } else {
             }
         } else {
         }
     }*/

    }

    public function getdetails(Request $request)
    {
        $id = $request->input('id');
        $router = Router::where('id', $id)->first();
        if ($router) {
            $router->load('owner', 'location', 'distributor', 'model');
        }

        $status = 'NA';
        if (isset($router->lastOnline)) {
            $status = $router->lastOnline >= Carbon::now()->subMinutes(3) ? 'online' : 'offline';
        }

        $router->status = $status;

        $lastWifiStatus = WiFiStatus::where('wifi_router_id', $id)
        ->orderBy('created_at', 'desc')
        ->select('cpu_usage', 'disk_usage', 'ram_usage')
        ->first();

        $router->usage = $lastWifiStatus;
        
        $wifiProfile = null;
        $settings = [];
        if ($router && $router->location_id) {
            $wifiProfile = \App\Models\WifiConfigurationProfiles::find($router->location->wifi_configuration_profile_id);
            if ($wifiProfile && $wifiProfile->settings) {
                // dd($wifiProfile->settings);
                $settings = json_decode($wifiProfile->settings, true); // decode as array
            }

        }

        // $uptime = json_decode($this->routeruptime($id)->getContent());
        $uptime = $this->routeruptime($id);

        return view('inventory-details', [
            'router' => $router,
            'wifiProfile' => $wifiProfile,
            'settings' => $settings,
            'uptime' => $uptime[0]->online_percent ?? 0
            ]
        );

    }


    public function dashboard()
    {
        return view('inventory-dashboard');
    }


    public function locationRouter1()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $user = User::find($user->parent_id);
        }

        if ($user->isPDO()) {
            $routers = \App\Models\Router::where('pdo_id', $user->id)
                ->where('location_id', '>', 0)
                ->select('pdo_id', 'location_id', 'lastOnline')
                ->get();

                $result = [];
                foreach ($routers as $router) {
                    $status = $router->lastOnline ? 'online' : 'offline';
                    $result[$router->location_id][][$status][] = $router;
                }
                return response()->json($result);
        }else{
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }

    public function locationRouter()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $user = User::find($user->parent_id);
        }

        if ($user->isPDO()) {
            $routers = \App\Models\Router::with('location:id,name')->where('pdo_id', $user->id)
                ->where('location_id', '>', 0)
                ->select('pdo_id', 'location_id', 'lastOnline', 'name')
                ->get();

            $result = [];
            foreach ($routers as $router) {
                $isOnline = $router->lastOnline && Carbon::parse($router->lastOnline)->gte(Carbon::now()->subMinutes(3));
                $statusKey = $isOnline ? 'online' : 'offline';

                $routerArr = [
                    'pdo_id' => $router->pdo_id,
                    'location_id' => $router->location_id,
                    'lastOnline' => $router->lastOnline,
                    'isOnline' => $isOnline,
                    'location_name' => $router->location->name ?? '',
                ];

                // Group by location_id, then status
                if (!isset($result[$router->location->name][0])) {
                    $result[$router->location->name][0] = [
                        'online' => [],
                        'offline' => []
                    ];
                }
                $result[$router->location->name][0][$statusKey][] = $routerArr;
            }
            return response()->json($result);
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    }


    public function routeruptime($routerId)
    {
        $days = '- 1 month';
        $count = 30 * 24 * 60;

        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');

        $live_pdo_uptime = Location::leftJoin('routers', 'locations.id', '=', 'routers.location_id')
            ->leftJoin('wi_fi_statuses', 'routers.id', '=', 'wi_fi_statuses.wifi_router_id')
            ->select('locations.id as location_id', 'locations.name as location_name', DB::raw('count(wi_fi_statuses.id) as online_percent'))
            ->where('wi_fi_statuses.created_at', '>', $date)
            ->where('routers.id', $routerId)
            ->groupBy('locations.id', 'locations.name')
            ->orderBy('online_percent', 'desc')
            // dd($live_pdo_uptime->toSql());
            ->get();

        for ($i=0; $i < count($live_pdo_uptime); $i++) {
            if(is_null($live_pdo_uptime[$i]['online_percent'])) $live_pdo_uptime[$i]['online_percent'] = 0;
            $live_pdo_uptime[$i]['online_percent'] = $live_pdo_uptime[$i]['online_percent']*100 / $count;

        }

        return $live_pdo_uptime;
        // return response()->json([
        //     'success' => true,
        //     'data' => $live_pdo_uptime
        // ]);

    }

    public function apRetire(Request $request){

        if (Auth::user()->isPDO()) {
            $router = Router::findOrFail($request->id);
            if ($router) {
                if ($request->plantype === "retire") { 
                    $router->retired = 1;
                    $router->save();

                    return response()->json([
                        'retired' => $request->id,
                        'message' => 'The Router has been retired successfully!'
                    ], 200);
                } else if ($request->plantype === "unretire") {
                    $router->retired = 0;
                    $router->save();

                    return response()->json([
                        'retired' => $request->id,
                        'message' => 'The Router has been unretired successfully!'
                    ], 200);
                } else {
                    return response()->json([
                        'retired' => $request->id,
                        'message' => 'Please try again!'
                    ], 200);
                }
            }
        }

        if (Auth::user()->isPDO() || Auth::user()->isDistributor()) {
            return response()->json([
                'suspended' => $request,
                'message' => 'you are not allowed to retired any Router'
            ], 200);
        }

    }

    public function controler_device(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $user = User::find($user->parent_id);
        }

        $apFilter = $request->get('apFilter');
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");
        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');
        $columnIndex = $columnIndex_arr[0]['column'];
        $columnName = $columnName_arr[$columnIndex]['data'];
        $columnSortOrder = $order_arr[0]['dir'];
        $searchValue = $search_arr['value'];

        $baseQuery = Router::leftJoin('users', 'routers.owner_id', '=', 'users.id')
            ->leftJoin('locations', 'routers.location_id', '=', 'locations.id')
            ->leftJoin('models', 'routers.model_id', '=', 'models.id')
            ->select(
                'routers.*',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) as owner_name"),
                'locations.name as location_name',
                'models.name as model_name',
                'locations.owner_id as location_owner_id'
            )
            ->where(function ($q) use ($searchValue) {
                $q->where('users.first_name', 'like', '%' . $searchValue . '%')
                    ->orWhere('locations.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.name', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.mac_address', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.eth1', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.wireless1', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.wireless2', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.serial_number', 'like', '%' . $searchValue . '%')
                    ->orWhere('routers.status', 'like', '%' . $searchValue . '%')
                    ->orWhere('models.name', 'like', '%' . $searchValue . '%');
            });
        $baseQuery->where("inventory_type", 'controller');    
        // Apply assignment filter
        if ($request->assignment == 'unassigned') {
            $baseQuery->unassigned($request->get('with_location'));
        }

        // Role-based filters
        if ($user->isDistributor()) {
            $distributor = Distributor::where('owner_id', $user->id)->first();
            $baseQuery->where('distributor_id', $distributor->id);

            $baseQuery->where("inventory_type", 'controller'); 
        }

        if ($user->isPDO()) {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('pdo_id', $user->id)
                    ->orWhere('users.id', $user->id)
                    ->orWhere('routers.owner_id', $user->id)
                    ->orWhereHas('location', function ($l) use ($user) {
                        $l->where('locations.owner_id', $user->id);
                    });
            });

            if ($apFilter === 'offline') {
                $baseQuery->where(function ($q) {
                    $q->whereNull('routers.lastOnline')->orWhere('lastOnline', '<', now()->subMinutes(3));
                });
            } elseif ($apFilter === 'online') {
                $baseQuery->where('lastOnline', '>=', now()->subMinutes(3));
            }elseif ($apFilter === 'retired') {
                $baseQuery->where('retired', 1);
            }

            $baseQuery->where("inventory_type", 'controller'); 
        }

        if ($user->isAdmin()) {
            if ($apFilter === 'spare-aps') {
                $baseQuery->whereNull('routers.location_id')->whereNull('routers.owner_id');
            } elseif ($apFilter === 'assigned-aps') {
                $baseQuery->where(function ($q) {
                    $q->whereNotNull('routers.location_id')->orWhereNotNull('routers.owner_id');
                });
            } elseif ($apFilter === 'offline') {
                $baseQuery->where(function ($q) {
                    $q->whereNull('routers.lastOnline')->orWhere('lastOnline', '<', now()->subMinutes(3));
                });
            } elseif ($apFilter === 'online') {
                $baseQuery->where('lastOnline', '>=', now()->subMinutes(3));
            }elseif ($apFilter === 'retired') {
                $baseQuery->where('retired', 1);
            }
            $baseQuery->where("inventory_type", 'controller'); 
        }

        // Clone for counts
        $countQuery = clone $baseQuery;
        $totalRecords = $countQuery->count();
        $totalRecordswithFilter = $totalRecords;
        // Fetch paginated data
        $sortableColumns = [
            'device' => '',
            'name' => 'routers.name',
            'mac_address' => 'routers.mac_address',
            'eth1' => 'routers.eth1',
            'wireless1' => 'routers.wireless1',
            'wireless2' => 'routers.wireless2',
            'serial_number' => 'routers.serial_number',
            'status' => 'routers.status',
            'owner' => 'users.first_name',
            'location_name' => 'locations.name',
            'model_name' => 'models.name',
            'batch_no' => 'routers.batch_no'
        ];
        $orderByColumn = $sortableColumns[$columnName] ?? 'routers.created_at';
        $routers = $baseQuery
            // ->orderBy($columnName ?: 'routers.created_at', $columnSortOrder ?: 'desc')
            ->orderBy($orderByColumn, $columnSortOrder ?: 'desc')
            ->skip($start)
            ->take($rowperpage)
            ->get();
        // Optionally eager load relationships after fetching
        $routers->load(['owner', 'location', 'distributor', 'model']);

        $data_arr = [];
        foreach ($routers as $router) {
            $status = 'NA';
            if (isset($router->lastOnline)) {
                $status = $router->lastOnline >= Carbon::now()->subMinutes(3) ? 'online' : 'offline';
            }
            $firmwares = ModelFirmwares::where('model_id', $router->model_id)->where('released', 1)->get();
            $data_arr[] = [
                'id' => $router->id,
                'device_img' => null,
                'device_type' => strtoupper($router->inventory_type),
                'name' => $router->name ?? null,
                'model' => $router->model ?? null,
                'location' => $router->location->name ?? null,
                'mac_address' => $router->mac_address,
                'eth1' => $router->eth1,
                'wireless1' => $router->wireless1,
                'wireless2' => $router->wireless2,
                'serial_number' => $router->serial_number,
                'status' => $status,
                'owner' => $router->owner_name,
                'location_id' => $router->location_id ?? null,
                'distributor_id' => $router->distributor_id ?? null,
                'firmwareVersion' => $router->firmwareVersion ?? null,
                'firmwares' => $firmwares,
                'latest_version' => $router->latest_tar_version ?? null,
                'retired' => $router->retired,
                'batch_no' => $router->batch_no ?? null
            ];
        }

        return response()->json([
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        ]);
    }

}
