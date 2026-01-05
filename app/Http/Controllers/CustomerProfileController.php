<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileForm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerProfileController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            /*'name' => 'required|max:255',
            'email' => 'required|email',*/
            'current_password' => 'nullable|max:100',
            'new_password' => 'nullable|required_with:current_password|min:8',
            'password_confirmation' => 'nullable|required_with:current_password|same:new_password',
        ]);

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        if ($user->role != config('constants.roles.customer')) {
            throw ValidationException::withMessages(['Sorry! You cannot use this feature. Please login to admin portal.']);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages(['Invalid Current Password Provided.']);
        }

        $data = $request->only('email');
        $data['first_name'] = $request->name;
        $user->fill($data);
        $user->password = bcrypt($request->new_password);
        $user->save();

        return $request->user();
    }
}
