<?php

namespace App\Http\Controllers;

use App\Models\State;
use App\Models\PinCode;
use App\Models\District;
use App\Models\Distributor;
use App\Models\DistributorPlan;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\CreateDistributionPlanRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DistributorPlanController extends Controller
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

        return DistributorPlan::query()->get();
    }

    public function store(CreateDistributionPlanRequest $form)
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

    public function show(DistributorPlan $plan)
    {
        $plan->pdo_id = json_decode($plan->pdo_id, true);
        return $plan;
    }

    public function update($id, CreateDistributionPlanRequest $form)
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
        return $form->update($id);
    }

    public function planDetails($id)
    {
        $distributor = Distributor::where('id', $id)->with('distributorPlan')->get();
        $area_name = $distributor[0]->distributorPlan->area_name;
        $data = array();
        if ($area_name == 'Pincode') {
            $data['PinCode'] = PinCode::all();
        }

        if ($area_name == 'District') {
            $data['District'] = District::all();
        }

        if ($area_name == 'State') {
            $data['State'] = State::all();
        }

        return response()->json(['area_name' => $area_name, 'data' => $data]);
    }

}
