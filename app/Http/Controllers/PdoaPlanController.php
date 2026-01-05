<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInternetPlanForm;
use App\Http\Requests\CreatePdoaPlanForm;
use App\Models\InternetPlan;
use App\Models\PdoaPlan;
use App\Models\Distributor;
use App\Models\DistributorPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PdoaPlanController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_plans_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        return PdoaPlan::query()->get();
    }


    public function store(CreatePdoaPlanForm $form)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_plans_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        return $form->save();
    }

    // public function show(PdoaPlan $plan)
    // {
    //     return $plan;
    // }

    public function show(Request $request)
    {
        $plan = PdoaPlan::with('users:pdo_type')->where('id', $request->plan)->first();
        return $plan;
    }



    public function pdo_plan(PdoaPlan $plan)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_plans_update'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        if ($user->isAdmin()) {
            $pdo_plan = PdoaPlan::query()->get();
        } else {
            if ($user->isDistributor()) {
                $get_plan_id = Distributor::with('DistributorPlan')->where('owner_id', $user->id)->first();
                if (is_array(json_decode($get_plan_id->DistributorPlan->pdo_id))) {
                    $pdo_plan = PdoaPlan::whereIn('id', array_merge(json_decode($get_plan_id->DistributorPlan->pdo_id)))->get();
                } else {
                    $pdo_plan = PdoaPlan::where('id', $get_plan_id->DistributorPlan->pdo_id)->get();
                }
            } else {
                $pdo_plan = [];
            }
        }

        return $pdo_plan;
    }

    public function getPlanDetail(Request $request)
    {
        $plan = PdoaPlan::where('id', $request->planId)->get();
        return $plan;
    }

    public function deletePdoPlan(Request $request)
    {
        if(!$request->id){
           return response()->json(['message' => 'Something went wrong Deleting Model', 'error' => "PDO ID is missing"], 500); 
        }

        try{
            $pdoPlan = PdoaPlan::findOrFail($request->id);

            // Check if inventory is assigned to this model_id
            $inventoryExists = User::where('pdo_type', $pdoPlan->id)->exists();

            if ($inventoryExists) {
                return response()->json([
                    'message' => 'PDO plan has been assigned, cannot delete.'
                ], Response::HTTP_NON_AUTHORITATIVE_INFORMATION); //203
            }

            $pdoPlan->delete();

            return response()->json([
                'message' => 'PDO Plan deleted successfully.',
            ],200);
        }
        catch(\Exception $e){
            Log::error('Issue on Deleting PDO Plan', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Something Went Deleting Model', 'error' => $e->getMessage()], 500);
        }
    }

    public function suspendPdoPlan(Request $request)
    {

        if (Auth::user()->isAdmin()) {
            $pdoaPlan = pdoaPlan::where('id', $request->id)->first();
            if ($pdoaPlan) {
                if ($request->plantype === "suspend") { 
                    $pdoaPlan->suspended = 1;
                    $pdoaPlan->save();

                    return response()->json([
                        'suspended' => $request->id,
                        'message' => 'The PDO Plan has been suspended successfully!'
                    ], 200);
                } else if ($request->plantype === "activate") {
                    $pdoaPlan->suspended = 0;
                    $pdoaPlan->save();

                    return response()->json([
                        'suspended' => $request->id,
                        'message' => 'The PDO Plan has been activated successfully!'
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
