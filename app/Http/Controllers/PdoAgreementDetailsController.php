<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\PdoAgreementDetails;
use App\Http\Requests\CreateAgreementRequest;

class PdoAgreementDetailsController extends Controller
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

        $router_id = (isset($_GET['router_id'])) ? $_GET['router_id'] : ""; 

        if($router_id != "") {
            $agreement_details = PdoAgreementDetails::with('owner')->where('router_id', $router_id)->orderBy('id', 'DESC')->get();
        } else {
            $agreement_details = PdoAgreementDetails::with('owner')->orderBy('id', 'DESC')->get();
        }
        return $agreement_details;
    }

    public function store(CreateAgreementRequest $form)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        return $form->save();
    }

    public function show($id)
    {
        $data['agreement_details'] = PdoAgreementDetails::with('owner')->find($id);
        return $data;
    }

    public function update($id, CreateAgreementRequest $form)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        return $form->update($id);
    }

    public function updateStatus(Request $request)
    {
        $updateStatus = PdoAgreementDetails::where('id', $request->agreement_id)->update([
            'agreement_status' => $request->status
        ]);
        return $updateStatus;
    }
}