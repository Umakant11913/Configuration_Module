<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Profile;
use App\Models\GSTFiles;
use App\Models\Distributor;
use App\Models\DistributorLocations;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Http\FormRequest;

class CreateDistributionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    protected $user;

    // public function authorize()
    // {
    //     $user = Auth::user();
    //     return $user->isAdmin();
    // }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id' => 'nullable|numeric',
            'first_name' => 'required|max:150',
            'last_name' => 'required|max:150',
            'email' => 'required|email',
            'password' => 'required_without:id|max:150',
            'company_name' => 'nullable|max:150',
            'gst_no' => 'nullable|max:150',
            'address' => 'nullable|max:255',
            'city' => 'nullable|max:255',
            'postal_code' => 'nullable|max:10',
            'pdo_type' => 'nullable|integer',
            'renewal_date' => 'nullable',
            'exclusive' => 'nullable|max:150',
        ];
    }

    public function save()
    {
        $userD = Auth::user();
        if ($userD->parent_id) {
            $parent = User::where('id', $userD->parent_id)->first();
            $userD = $parent;
        }
        $distributor_plan_value = ($this->distributor_plan_value) ?? $this->distributor_plan_value;
        $total_area = $this->no_of_area;
        try {

            DB::beginTransaction();

            // check folder exists
            $folder = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/distributor/';
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            $checkEmail = User::where('email', $this->email)->get();

            if (($total_area < count($distributor_plan_value))) {
                return response()->json(['message' => 'Selected area does not matched with plan'], 404); // Status code here
            }

            if (count($checkEmail) > 0) {
                return response()->json(['message' => 'Email id already exist!!'], 404); // Status code here
            } else {

                $user = new User();
                $user->fill($this->only('first_name', 'last_name', 'email', 'pdo_type'));
                $user->password = bcrypt($this->password);
                $user->role = config('constants.roles.distributor');
                $user->is_parentId = $userD->id;
                $user->distributor_type = $this->distributor_type;
                $user->sub_distributor_comission = ($this->sub_distributor_commission) ? $this->sub_distributor_commission : 0;
                $user->save();
                $user->roles()->sync('3');

                $profile = $user->profile;
                if (!$profile) {
                    $profile = new Profile();
                    $profile->user_id = $user->id;
                }
                $profile->fill($this->only('company_name', 'address', 'city', 'postal_code'));
                $profile->save();

                do {
                    $randomId = Uuid::uuid4()->toString();
                    $idExists = Distributor::where('dis_id', $randomId)->exists();
                } while ($idExists);

                if (!$idExists) {
                    // Insert Record.
                    $distributor = new Distributor();
                    $distributor->owner_id = $user->id;
                    $distributor->dis_id = $randomId;
                    $distributor->contract = ($this->contract) ? $this->contract->getClientOriginalName() : '';
                    $distributor->gst_no = ($this->gst_type == 1) ? $this->gst_no : '';
                    $distributor->fill($this->only('distributor_plan', 'renewal_date', 'exclusive', 'gst_type'));
                    $distributor->parent_dId = $userD->id; // display data for specific
                    $distributor->status = 0;
                    $distributor->save();

                    // Insert Distributor location data
                    foreach ($distributor_plan_value as $key => $val) {
                        $distributor_loc = new DistributorLocations();
                        $distributor_loc->dist_id = $distributor->id;
                        $distributor_loc->dist_plan_id = $this->distributor_plan;
                        $distributor_loc->no_of_area = $this->no_of_area;
                        $distributor_loc->area_name = $this->area_name;
                        $distributor_loc->selected_area = $val;
                        $distributor_loc->save();
                    }

                    //Store GST files data with specific distributor ID
                    if ($this->gst_type == 1) {
                        $gstFiles = new GSTFiles();
                        $gstFiles->distributor_id = $distributor->id;
                        $gstFiles->photo = ($this->gst_photo) ? $this->gst_photo->getClientOriginalName() : '';
                        $gstFiles->address_proof = ($this->gst_address) ? $this->gst_address->getClientOriginalName() : '';
                        $gstFiles->id_proof = ($this->gst_ID_proof) ? $this->gst_ID_proof->getClientOriginalName() : '';
                        $gstFiles->company_proof = ($this->gst_company_proof) ? $this->gst_company_proof->getClientOriginalName() : '';
                        $gstFiles->bank_details = ($this->gst_bank_details) ? $this->gst_bank_details->getClientOriginalName() : '';
                        $gstFiles->save();
                    }
                }

                // file upload using distributor id
                $GSTFolder = $folder . $distributor->id . '/GST/';
                $ContractFolder = $folder . $distributor->id . '/Contract/';

                if ($distributor->id) {
                    if ($this->gst_photo) {
                        $GST_File = $this->gst_photo;
                        $GST_FileName = 'Photo-' . $GST_File->getClientOriginalName();
                        $GST_File->move($GSTFolder, $GST_FileName);
                    }
                    if ($this->gst_address) {
                        $GST_File = $this->gst_address;
                        $GST_FileName = 'ADDRESS-' . $GST_File->getClientOriginalName();
                        $GST_File->move($GSTFolder, $GST_FileName);
                    }
                    if ($this->gst_ID_proof) {
                        $GST_File = $this->gst_ID_proof;
                        $GST_FileName = 'ID_PROOF-' . $GST_File->getClientOriginalName();
                        $GST_File->move($GSTFolder, $GST_FileName);
                    }
                    if ($this->gst_company_proof) {
                        $GST_File = $this->gst_company_proof;
                        $GST_FileName = 'COMPANY_PROOF-' . $GST_File->getClientOriginalName();
                        $GST_File->move($GSTFolder, $GST_FileName);
                    }
                    if ($this->gst_bank_details) {
                        $GST_File = $this->gst_bank_details;
                        $GST_FileName = 'BANK_DETAILS-' . $GST_File->getClientOriginalName();
                        $GST_File->move($GSTFolder, $GST_FileName);
                    }
                    // contact file
                    if ($this->contract) {
                        $CONTRACT_File = $this->contract;
                        $CONTRACT_FileName = 'CONTRACT-' . $CONTRACT_File->getClientOriginalName();
                        $CONTRACT_File->move($ContractFolder, $CONTRACT_FileName);
                    }
                }

                DB::commit();

                return $user;

            }

        } catch (Exception $e) {

            return [];

        }


    }

    public function update($id)
    {
        DB::beginTransaction();

        // check folder exists
        $folder = $_SERVER['DOCUMENT_ROOT'] . '/assets/uploads/distributor/';
        $userD = Auth::user();
        if ($userD->parent_id) {
            $parent = User::where('id', $userD->parent_id)->first();
            $userD = $parent;
        }

        $user = User::find($this->ownerid);
        $user->fill($this->only('first_name', 'last_name', 'email'));
        $user->password = ($this->password) ? bcrypt($this->password) : $user->password;
        $user->is_parentId = $userD->id;
        $user->distributor_type = ($this->distributor_type) ?? $this->distributor_type;
        $user->sub_distributor_comission = ($this->sub_distributor_commission) ? $this->sub_distributor_commission : 0;
        $user->save();

        $profile = $user->profile;
        if (!$profile) {
            $profile = Profile::where('user_id', $this->ownerid);
            $profile->user_id = $user->id;
        }
        $profile->fill($this->only('company_name', 'address', 'city', 'postal_code'));
        $profile->save();

        // Insert Record.
        $distributor = Distributor::find($this->id);
        $distributor->gst_no = ($this->gst_type == 1) ? $this->gst_no : null;
        $distributor->contract = ($this->contract) ? $this->contract->getClientOriginalName() : $distributor->contract;
        $distributor->fill($this->only('distributor_plan', 'renewal_date', 'exclusive', 'gst_type'));
        $distributor->save();

        if ($this->gst_type == 1) {
            $gstFiles = new GSTFiles();
            $gstFiles->distributor_id = $distributor->id;
            $gstFiles->photo = ($this->gst_photo) ? $this->gst_photo->getClientOriginalName() : $gstFiles->photo;
            $gstFiles->address_proof = ($this->gst_address) ? $this->gst_address->getClientOriginalName() : $gstFiles->address_proof;
            $gstFiles->id_proof = ($this->gst_ID_proof) ? $this->gst_ID_proof->getClientOriginalName() : $gstFiles->id_proof;
            $gstFiles->company_proof = ($this->gst_company_proof) ? $this->gst_company_proof->getClientOriginalName() : $gstFiles->company_proof;
            $gstFiles->bank_details = ($this->gst_bank_details) ? $this->gst_bank_details->getClientOriginalName() : $gstFiles->bank_details;
            $gstFiles->save();
        } else {
            $gstFiles = GSTFiles::where('distributor_id', $this->id)->delete();
        }

        // file upload using distributor id
        $GSTFolder = $folder . $distributor->id . '/GST/';
        $ContractFolder = $folder . $distributor->id . '/Contract/';

        if (!file_exists($GSTFolder)) {
            mkdir($GSTFolder, 0777, true);
        }

        if (!file_exists($ContractFolder)) {
            mkdir($ContractFolder, 0777, true);
        }

        $scanGST = scandir($GSTFolder);
        $scanContract = scandir($ContractFolder);


        if ($this->gst_type == 1) {
            if ($this->gst_photo) {
                foreach ($scanGST as $file) {
                    if (!is_dir($folder . '/' . $file)) {
                        if (str_contains($file, 'Photo-')) {
                            unlink($GSTFolder . "/" . $file);
                        }
                    }
                }
                $GST_File = $this->gst_photo;
                $GST_FileName = 'Photo-' . $GST_File->getClientOriginalName();
                $GST_File->move($GSTFolder, $GST_FileName);
            }
            if ($this->gst_address) {
                foreach ($scanGST as $file) {
                    if (!is_dir($folder . '/' . $file)) {
                        if (str_contains($file, 'ADDRESS-')) {
                            unlink($GSTFolder . "/" . $file);
                        }
                    }
                }
                $GST_File = $this->gst_address;
                $GST_FileName = 'ADDRESS-' . $GST_File->getClientOriginalName();
                $GST_File->move($GSTFolder, $GST_FileName);
            }
            if ($this->gst_ID_proof) {
                foreach ($scanGST as $file) {
                    if (!is_dir($folder . '/' . $file)) {
                        if (str_contains($file, 'ADDRESS-')) {
                            unlink($GSTFolder . "/" . $file);
                        }
                    }
                }
                $GST_File = $this->gst_ID_proof;
                $GST_FileName = 'ID_PROOF-' . $GST_File->getClientOriginalName();
                $GST_File->move($GSTFolder, $GST_FileName);
            }
            if ($this->gst_company_proof) {
                foreach ($scanGST as $file) {
                    if (!is_dir($folder . '/' . $file)) {
                        if (str_contains($file, 'COMPANY_PROOF-')) {
                            unlink($GSTFolder . "/" . $file);
                        }
                    }
                }
                $GST_File = $this->gst_company_proof;
                $GST_FileName = 'COMPANY_PROOF-' . $GST_File->getClientOriginalName();
                $GST_File->move($GSTFolder, $GST_FileName);
            }
            if ($this->gst_bank_details) {
                foreach ($scanGST as $file) {
                    if (!is_dir($folder . '/' . $file)) {
                        if (str_contains($file, 'BANK_DETAILS-')) {
                            unlink($GSTFolder . "/" . $file);
                        }
                    }
                }
                $GST_File = $this->gst_bank_details;
                $GST_FileName = 'BANK_DETAILS-' . $GST_File->getClientOriginalName();
                $GST_File->move($GSTFolder, $GST_FileName);
            }
        } else {
            foreach ($scanGST as $file) {
                if (!is_dir($folder . '/' . $file)) {
                    unlink($GSTFolder . "/" . $file);
                }
            }
        }

        if ($this->contract) {
            foreach ($scanContract as $file) {
                if (!is_dir($folder . '/' . $file)) {
                    if (str_contains($file, 'CONTRACT-')) {
                        unlink($ContractFolder . "/" . $file);
                    }
                }
            }
            $CONTRACT_File = $this->contract;
            $CONTRACT_FileName = 'CONTRACT-' . $CONTRACT_File->getClientOriginalName();
            $CONTRACT_File->move($ContractFolder, $CONTRACT_FileName);
        }

        DB::commit();

        return $user;

    }

}
