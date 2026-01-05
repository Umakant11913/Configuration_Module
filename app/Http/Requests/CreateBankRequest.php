<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\PdoBankDetails;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\FormRequest;

class CreateBankRequest extends FormRequest
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
    // public function rules()
    // {
    //     return [
    //         'id' => 'nullable|numeric',
    //         'first_name' => 'required|max:150',
    //         'last_name' => 'required|max:150',
    //         'email' => 'required|email',
    //         'password' => 'required_without:id|max:150',
    //         'company_name' => 'nullable|max:150',
    //         'gst_no' => 'nullable|max:150',
    //         'address' => 'nullable|max:255',
    //         'city' => 'nullable|max:255',
    //         'postal_code' => 'nullable|max:10',
    //         'pdo_type' => 'nullable|integer',
    //         'renewal_date' => 'nullable',
    //         'exclusive' => 'nullable|max:150',
    //     ];
    // }

    public function save()
    {
        $userD = Auth::user();
        if ($userD->parent_id) {
            $parent = User::where('id', $userD->parent_id)->first();
            $userD = $parent;
        }
        try {
            DB::beginTransaction();
                    // Insert Record.
                    $bank_details = new PdoBankDetails();
                    $bank_details->pdo_owner_id = $this->pdo_owner_id;
                    $bank_details->name = $this->name;
                    $bank_details->bank_name = $this->bank_name;
                    $bank_details->account_number = $this->account_number;
                    $bank_details->branch = $this->branch;
                    $bank_details->account_type = $this->account_type;
                    $bank_details->ifsc_code = $this->ifsc_code;
                    $bank_details->is_primary = ($this->is_primary == 1) ? $this->is_primary : 0;
                    $bank_details->bank_status = 1;
                    $bank_details->save();
                DB::commit();
        } catch (Exception $e) {
            return [];
        }
    }

    public function update($id){

        DB::beginTransaction();
        // Insert Record.
        $bank_details = PdoBankDetails::find($this->id);
        $bank_details->pdo_owner_id = $this->pdo_owner_id;
        $bank_details->name = $this->name;
        $bank_details->bank_name = $this->bank_name;
        $bank_details->account_number = $this->account_number;
        $bank_details->branch = $this->branch;
        $bank_details->account_type = $this->account_type;
        $bank_details->ifsc_code = $this->ifsc_code;
        $bank_details->is_primary = ($this->is_primary == 1) ? $this->is_primary : 0;
        $bank_details->bank_status = 1;
        $bank_details->save();

        DB::commit();
    }

}