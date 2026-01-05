<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    public function get($parent, $token)
    {
        $invite = Invitation::where('token', $token)->first();
        if ($invite) {
            $email = $invite ? $invite->email : null;
            $parent_id = $parent;
            return view('auth.register', compact('email', 'parent_id'));
        } else {
            return view('components.invitation-error');
        }
    }

    public function store(Request $request)

    {
        $parent = User::where('id', $request->parent)->first();
        $check = Invitation::where('email', $request->email)->first();
        $roles = json_decode($check->roles);
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'role' => $parent->role,
            'password' => Hash::make($request->password),
            'parent_id' => $parent->id
        ]);

        $user->roles()->sync($roles);
        $user->save();

        $invitation_status = Invitation::where('email', $request->email)->first();
        $invitation_status->status = 1;
        $invitation_status->save();

        return redirect('/login');

    }

}
