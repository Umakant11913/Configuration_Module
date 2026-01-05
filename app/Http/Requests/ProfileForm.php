<?php

namespace App\Http\Requests;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileForm extends CreateLocationOwnerForm
{
    protected $user;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'first_name' => 'required|max:150',
            'last_name' => 'required|max:150',
            'email' => 'nullable|email',
            'company_name' => 'nullable|max:150',
            'gst_no' => 'nullable|max:150',
            'address' => 'nullable|max:255',
            'city' => 'nullable|max:255',
            'postal_code' => 'nullable|max:10',
        ];
    }

    protected function setup()
    {
        $this->user = Auth::user();
    }
}
