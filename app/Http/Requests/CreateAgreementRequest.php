<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Router;
use App\Models\PdoAgreementDetails;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\FormRequest;

class CreateAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    protected $user;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */

    public function save()
    {
        $userD = Auth::user();
        if ($userD->parent_id) {
            $parent = User::where('id', $userD->parent_id)->first();
            $userD = $parent;
        }
        if($this->router_id) {
            $routerDetails = Router::where('id',$this->router_id)->first();
        }
        try {
            DB::beginTransaction();
                    // Insert Record.
                    $agreement_details = new PdoAgreementDetails();
                    $agreement_details->pdo_owner_id = $routerDetails->owner_id;
                    $agreement_details->router_id = $this->router_id;
                    $agreement_details->revenue_share = $this->revenue_share;
                    $agreement_details->subscription_data = $this->subscription_data;
                    $agreement_details->sms_quota = $this->sms_quota;
                    $agreement_details->storage_quota = $this->storage_quota;
                    $agreement_details->email_quota = $this->email_quota;
                    $agreement_details->latitude = $this->latitude;
                    $agreement_details->longitude = $this->longitude;
                    $agreement_details->expiry_date = $this->expiry_date;
                    $agreement_details->end_date = $this->end_date;
                    $agreement_details->start_date = $this->start_date;
                    $agreement_details->agreement_status = 1;
                    $agreement_details->save();
                DB::commit();
        } catch (Exception $e) {
            return [];
        }
    }

    public function update($id){

        if($this->router_id) {
            $routerDetails = Router::where('id',$this->router_id)->first();
        }
        DB::beginTransaction();
        // Insert Record.
        $agreement_details = PdoAgreementDetails::find($this->id);
                    $agreement_details->pdo_owner_id = $routerDetails->owner_id;
                    $agreement_details->router_id = $this->router_id;
                    $agreement_details->revenue_share = $this->revenue_share;
                    $agreement_details->subscription_data = $this->subscription_data;
                    $agreement_details->sms_quota = $this->sms_quota;
                    $agreement_details->storage_quota = $this->storage_quota;
                    $agreement_details->email_quota = $this->email_quota;
                    $agreement_details->latitude = $this->latitude;
                    $agreement_details->longitude = $this->longitude;
                    $agreement_details->expiry_date = $this->expiry_date;
                    $agreement_details->end_date = $this->end_date;
                    $agreement_details->start_date = $this->start_date;
                    $agreement_details->agreement_status = 1;
        $agreement_details->save();

        DB::commit();
    }

}