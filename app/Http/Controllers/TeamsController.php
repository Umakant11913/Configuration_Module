<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamsController extends Controller
{
    public function index()
    {
        $parent = Auth::user();

        $teams = User::with('roles')->where('parent_id', $parent->id)->get();
        return $teams;
    }

    public function edit($team_id)
    {
        $user = User::with('roles')->where('id', $team_id)->first();
        return $user;
    }

    public function update(Request $request, $userId)
    {
        $roles = json_decode($request->input('roles'));
        $user = User::where('id', $userId)->first();
        $user->roles()->sync($roles);
        $user->save();
        return response()->json(['message' => "success", 'status' => 200]);
    }

    public function delete($id)
    {
        $user = User::where('id', $id)->first();
        $user->roles()->detach();
        $user->delete();
        return response()->json(['message' => "Team Member Deleted Successfully", 'status' => 200]);
    }

}
