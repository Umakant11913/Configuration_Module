<?php

namespace App\Http\Controllers;

use App\Mail\InviteUsersMail;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InvitationsController extends Controller
{
    public function index()
    {
        $parent = Auth::user()->id;
        $invitations = Invitation::where('status', 0)->where('created_by', $parent)->get();
        return $invitations;
    }

    public function store(Request $request)
    {
        $parent = Auth::user()->id;
        $user = User::where('email', $request->email)->first();
        $invitation_check = Invitation::where('email', $request->email)->where('status', 0)->first();

        if ($user) {
            return response()->json(['message' => "User Already Exist", 'status' => 400]);
        } else if ($invitation_check) {
            return response()->json(['message' => "Invitation Already Sent ", 'status' => 400]);
        } else {
            $random = \Illuminate\Support\Str::random(6);
            $invitation = Invitation::create([
                'email' => $request->email,
                'status' => 0,
                'token' => strtoupper($random),
                'roles' => $request->roles,
                'created_by' => $parent
            ]);
            $invitation->save();
            $employeeCredentials = ([
                'token' => $invitation->token,
                'parent_id' => $parent
            ]);

            $invitation->notify(new InviteUsersMail($employeeCredentials));
            return response()->json(['message' => "success", 'status' => 200]);
        }
    }

    public function resendInvite($user)
    {
        $parent = Auth::user()->id;
        $invitation = Invitation::where('id', $user)->where('status', 0)->first();
        $random = \Illuminate\Support\Str::random(6);
        $invitation->token = strtoupper($random);
        $invitation->save();
        $employeeCredentials = ([
            'token' => $random,
            'parent_id' => $parent
        ]);

        $invitation->notify(new InviteUsersMail($employeeCredentials));
        return response()->json(['message' => "success", 'status' => 200]);
    }

    public function delete($id)
    {
        $invite = Invitation::where('id', $id)->first();
        $invite->delete();
        return response()->json(['message' => "Invitation Deleted Successfully", 'status' => 200]);
    }

}
