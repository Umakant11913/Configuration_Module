<?php

namespace App\Http\Requests;

use App\Models\InternetPlan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateInternetPlanForm extends BaseRequest
{
    protected $plan;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|between:1,32',
            'description' => 'nullable|max:255',
            'validity' => 'required|integer',
            'session_duration' => 'required|integer',
            'data_limit' => 'nullable|integer',
            'data_roll_over' => 'nullable|integer',
            'bandwidth' => 'required|integer|gt:0',
            'price' => 'required|numeric',
            'session_duration_window' => 'nullable|integer',
            'id' => 'nullable|exists:internet_plans',
        ];
    }

    protected function setup()
    {
        $this->plan = new InternetPlan();
        if ($this->id) {
            $this->plan = InternetPlan::findOrFail($this->id);
        }
    }

    public function save()
    {
        //Log::info($this->add_on_type);
        $this->plan->fill($this->validated(),'add_on_type');
        $this->plan->petals = $this->plan->price * 100;
        $this->plan->plan_type = $this->plan->price == 0.00 ? 'free' : 'default';
        $this->plan->add_on_type = $this->add_on_type;
        $this->plan->save();
        return $this->plan;
    }

    public function saveInternetPlan()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $plan = new InternetPlan();
        $plan->name = $this->name;
        $plan->description = $this->description ?? null;
        $plan->validity = $this->validity != 0 ? $this->validity : 1 * 24 * 60;
        $plan->bandwidth = $this->bandwidth ?? null;
        $plan->data_limit = $this->data_limit ?? null;
        $plan->price = $this->price ?? 0.00;
        $plan->add_on_type = $this->add_on_type  ?? null;
        $plan->petals = 0;
        $plan->session_duration = $this->session_duration != 0 ? $this->session_duration : 60;
        $plan->data_roll_over = $this->data_roll_over ?? null;
        $plan->show_buy_plan = $this->show_buy_plan == 'on' ? 0 : 1;
            //$plan->profile_id = null;
            $plan->added_by = $user->id;
            if ($user->id !== 1) {
                $plan->plan_type = $this->price == 0.00 ? "free" : "paid";
            } else {
                $plan->plan_type = $this->price == 0.00 ? "free" : "default";
            }
            $plan->save();
        return $plan;
    }

    public function updateInternetPlan($id)
    {
        $plan = InternetPlan::find($id);
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        if ($plan) {
            $plan->name = $this->name;
            $plan->description = $this->description ?? null;
            $plan->validity = $this->validity != 0 ? $this->validity : 1 * 24 * 60;
            $plan->bandwidth = $this->bandwidth ?? null;
            $plan->data_limit = $this->data_limit ?? null;
            $plan->price = $this->price ?? 0.00;
            $plan->petals = 0;
            $plan->session_duration = $this->session_duration != 0 ? $this->session_duration : 60;
            $plan->data_roll_over = $this->data_roll_over ?? null;
            $plan->added_by = $user->id;
            $plan->show_buy_plan = $this->show_buy_plan == 'on' ? 0 : 1;
            $plan->add_on_type = $this->add_on_type  ?? null;

            if ($user->id !== 1) {
                $plan->plan_type = $this->price == 0.00 ? "free" : "paid";
            } else {
                $plan->plan_type = $this->price == 0.00 ? "free" : "default";
            }
            //$plan->plan_type = $this->price == 0.00 ? "free" : "default";

            $plan->save();

            return $plan;
        }

        return $plan;
    }
}
