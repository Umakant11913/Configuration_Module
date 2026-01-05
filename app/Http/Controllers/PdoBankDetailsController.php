<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\PdoBankDetails;
use App\Http\Requests\CreateBankRequest;

class PdoBankDetailsController extends Controller
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

        $bank_details = PdoBankDetails::with('owner')->orderBy('id', 'DESC')->get();
        return $bank_details;
    }

    public function store(CreateBankRequest $form)
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
        $data['bank_details'] = PdoBankDetails::with('owner')->find($id);
        return $data;
    }

    public function update($id, CreateBankRequest $form)
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
        $updateStatus = PdoBankDetails::where('id', $request->bank_id)->update([
            'bank_status' => $request->status
        ]);
        return $updateStatus;
    }
}