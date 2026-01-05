<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\GSTFiles;
use App\Models\PayoutLog;
use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CreateDistributionRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DistributorController extends Controller
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
            abort_if(Gate::denies('admin_accounts_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }


        if($title == 'Distributor')
        {
            abort_if(Gate::denies('distributor_account_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $data = array();
        if ($user->isAdmin()) {
            $distributor = Distributor::with('owner')->orderBy('id', 'DESC')->get();
        } else {
            $distributor = Distributor::with('owner')->where('parent_dId', $user->id)->orderBy('id', 'DESC')->get();
        }

        $i=0;
        foreach ($distributor as $key => $value) {
            // $payoutsData = PayoutLog::groupBy('pdo_owner_id')->selectRaw('sum(payout_amount) as payout_amount, pdo_owner_id')->get();
            $payoutsData = PayoutLog::where('pdo_owner_id', $value->owner->id)->get()->sum('payout_amount');
            $owner_id=$value->owner_id;
            $amount = 0;
            $nestedData = array();
            $profile = Profile::where('user_id', $value->owner_id)->first();
            $nestedData['id'] = $value->id;
            $nestedData['parent_id'] = $user->id;
            $nestedData['dis_id'] = $value->dis_id;
            $nestedData['owner_id'] = $value->owner_id;
            $nestedData['name'] = $value->owner->first_name .' '. $value->owner->last_name;
            $nestedData['role'] = $value->owner->role;
            $nestedData['email'] = $value->owner->email;
            $nestedData['company_name'] = isset($profile->company_name) ? $profile->company_name : '';
            $nestedData['gst_no'] = $value->gst_no;
            if($value->gst_type == 1) {
                $nestedData['gst_files'] = $_SERVER['DOCUMENT_ROOT'].'/assets/uploads/distributor/'.$value->id.'/GST-'.$value->gst_files;
            }
            $nestedData['address'] = isset($profile->address) ? $profile->address : '';
            $nestedData['city'] = isset($profile->city) ? $profile->city : '';
            $nestedData['postal_code'] = isset($profile->postal_code) ? $profile->postal_code : '';
            $nestedData['contract'] = $_SERVER['DOCUMENT_ROOT'].'/assets/uploads/distributor/'.$value->id.'/CONTRACT-'.$value->contract;
            $nestedData['status'] = $value->status;
            // add payout value
            // foreach($payoutsData as $payout){
            //     if($payout->pdo_owner_id == $owner_id){
            //         $amount = $payout->payout_amount;
            //     }
            //     $nestedData['payout_amount'] = $amount;
                $nestedData['payout_amount'] = $payoutsData;
            // }
            $i++;
            $data[] =$nestedData;
        }

        return $data;

    }

    public function store(CreateDistributionRequest $form)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_accounts_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        if($title == 'Distributor')
        {
            abort_if(Gate::denies('distributor_account_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        return $form->save();
    }

    public function show($id)
    {
        $data['distributor'] = Distributor::with('owner')->find($id);
        $data['profile'] = Profile::where('user_id', $data['distributor']->owner_id)->first();
        $data['gst_files'] = GSTFiles::where('distributor_id', $data['distributor']->id)->first();

        return $data;
    }

    public function update($id, CreateDistributionRequest $form)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_accounts_update'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        if($title == 'Distributor')
        {
            abort_if(Gate::denies('distributor_account_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        return $form->update($id);
    }

    public function updateStatus(Request $request)
    {
        $updateStatus = Distributor::where('id', $request->owner_id)->update([
            'status' => $request->status
        ]);

        return $updateStatus;
    }

    public function getUploadedFiles($id)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        // if (!$user->isAdmin() || !$user->isDistributor()) {
        //     return [];
        // }

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $url = "{$protocol}://{$host}";

        $distributor = Distributor::where('id', $id)->first();

        $folder = $url.'/assets/uploads/distributor/' . $distributor->id;

        $nestedData = array();
        $nestedData['id'] = $id;
        if($distributor->gst_type == 1) {
            $filesData = GSTFiles::where('distributor_id', $id)->first();
            $nestedData['photo'] = $filesData->photo ? $folder . '/GST/' . 'Photo-' . $filesData->photo : '#';
            $nestedData['address_proof'] = $filesData->photo ? $folder . '/GST/' . 'ADDRESS-' . $filesData->address_proof : '#';
            $nestedData['id_proof'] = $filesData->photo ? $folder . '/GST/' . 'ID_PROOF-' . $filesData->id_proof : '#';
            $nestedData['company_proof'] = $filesData->photo ? $folder . '/GST/' . 'COMPANY_PROOF-' . $filesData->company_proof : '#';
            $nestedData['bank_details'] = $filesData->photo ? $folder . '/GST/' . 'BANK_DETAILS-' . $filesData->bank_details : '#';
        }
        $nestedData['contract'] = $distributor->contract ? $folder . '/Contract/' . 'CONTRACT-' . $distributor->contract : '#';

        return $nestedData;
    }


}
