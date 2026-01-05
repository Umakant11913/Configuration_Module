<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileForm;
use App\Models\PdoCredits;
use App\Models\PdoCreditsHistory;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function store(ProfileForm $form)
    {
        return $form->save();
    }

    public function updateProfilePic(Request $request)
    {
        $request->validate([
            'picture' => 'required|mimes:jpg,jpeg,png|max:2048',
        ]);
        $file = $request->file('picture');
        $extension = $file->getClientOriginalExtension();
        $filename = date('YmdHis') . Str::random(12) . '.' . $extension;
        $storedFileName = $file->storeAs('public', $filename);

        if (!$storedFileName) {
            throw ValidationException::withMessages(['Error while saving file ' . $file->getClientOriginalName()]);
        }
        $user = Auth::user();
        $user->photo = $storedFileName;
        $user->save();
        return $user;
    }

    public function basic(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required|confirmed']);
        $user = Auth::user();
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->save();
        return $user;
    }

    public function profileUpdate(Request $request)
    {
        $request->validate([
            'password' => 'nullable|confirmed',
            'first_name' => 'required|max:150',
            'last_name' => 'required|max:150',
            'email' => 'nullable|email',
            'company_name' => 'nullable|max:150',
            'gst_no' => 'nullable|max:150',
            'address' => 'nullable|max:255',
            'city' => 'nullable|max:255',
            'postal_code' => 'nullable|max:10'
        ]);

        $user = Auth::user();

        if ($request->email && $request->email !== $user->email) {
            $emailExists = User::where('email', $request->email)->exists();
            if ($emailExists) {
                return back()->withErrors(['email' => 'The email address is already in use.'])->withInput();
            }
            $user->email = $request->email;
        }

        // Update other profile fields
        if ($request->password) {
            $user->password = bcrypt($request->password);
        }
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->save();

        $profile = Profile::where('user_id', $user->id)->first();

        if ($profile) {
            $profile->company_name = $request->company_name;
            $profile->gst_no = $request->gst_no;
            $profile->address = $request->address;
            $profile->city = $request->city;
            $profile->postal_code = $request->postal_code;
            $profile->save();
        } else {
            $profile = new Profile();
            $profile->user_id = $user->id; // Make sure to associate the profile with the user
            $profile->company_name = $request->company_name;
            $profile->gst_no = $request->gst_no;
            $profile->address = $request->address;
            $profile->city = $request->city;
            $profile->postal_code = $request->postal_code;
            $profile->save();
        }

        return $user;

    }


    public function show(Request $form)
    {
        $user = Auth::user();
        $user->load('profile');
        return $user;
    }

    public function subscription()
    {
        $user = Auth::user();
        $pdo_credits = PdoCredits::where('pdo_id',$user->id)->first();

        return response()->json([
            "message" =>"Success",
            'data'=> $pdo_credits,
        ],200);
    }
}
