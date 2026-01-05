<?php

namespace App\Http\Requests;

use App\Models\PdoaPlan;

class CreatePdoaPlanForm extends BaseRequest
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
            'commission' => 'required|numeric',
            'acquisition_commission' => 'required|numeric',
            'acquisition_commission_duration' => 'required|integer',
            'distributor_commission' => 'required|numeric',
             'service_fee' => 'required|numeric',
            'id' => 'nullable|exists:pdoa_plans',
        ];
    }

    protected function setup()
    {
        $this->plan = new PdoaPlan();
        if ($this->id) {
            $this->plan = PdoaPlan::findOrFail($this->id);
        }
    }

    public function save()
    {
        // check PDO and Distributor total commission
        $total_commission = $this->commission + $this->distributor_commission;
        if ($total_commission>100) {
            return response()->json(['message' => 'PDO and Distributor commission is more than 100%'], 404); // Status code here
        } else {
            $this->plan->fill($this->validated() + $this->only('service_fee', 'contract_length', 'credits', 'validity_period', 'sms_quota', 'grace_period'));
            $this->plan->save();
            return $this->plan;
        }
    }
}
