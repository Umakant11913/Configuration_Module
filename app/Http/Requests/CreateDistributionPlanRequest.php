<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\DistributorPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class CreateDistributionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        return $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'area_name' => 'required',
            'number_of_area' => 'numeric',
            'number_of_device' => 'numeric',
            'target_type' => 'required',
            'target_device' => 'numeric',
            'pdo_id' => 'required',
        ];
    }

    public function save()
    {
        try {

        if(strlen($this->name) > 100) {
            return response()->json(['message' => 'Plan name text length is too more'], 404); // Status code here
        }

        DB::beginTransaction();

        $distributor_plan = new DistributorPlan();
        // $distributor_plan->fill($this->only('name', 'area_name', 'number_of_area', 'number_of_device', 'target_type', 'target_device', 'pdo_id'));
        // $distributor_plan->pdo_id = 0;
        $distributor_plan->pdo_id = json_encode($this->pdo_id);
        $distributor_plan->fill($this->only('name', 'area_name', 'number_of_area', 'number_of_device', 'target_type', 'target_device'));
        $distributor_plan->save();

        DB::commit();

        return $distributor_plan;

        } catch(Exception $e) {

            return [];

        }
    }

    public function update($id)
    {
        try {

        if(strlen($this->name) > 100) {
            return response()->json(['message' => 'Plan name text length is too more'], 404); // Status code here
        }

        DB::beginTransaction();

        $distributor_plan = DistributorPlan::find($this->id);
        $distributor_plan->pdo_id = json_encode($this->pdo_id);
        $distributor_plan->fill($this->only('name', 'area_name', 'number_of_area', 'number_of_device', 'target_type', 'target_device')); //, 'pdo_id'
        $distributor_plan->save();

        DB::commit();

        return $distributor_plan;

        } catch(Exception $e) {

            return [];

        }
    }
}
