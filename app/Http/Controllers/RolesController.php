<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RolesController
{
    public function index()
    {
        $user = Auth::user();
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_roles_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }
        $roles = Role::with(['permissions'])->where('created_by', '=', $user->id)->orwhere('global_role', '=', 1)->get();
//        $roles = Role::with(['permissions'])->get();
        return $roles;
    }

    public function permissions()
    {
        $permissions = Permission::all();
        return $permissions;
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $role = $user->roles;
        $title = Arr::pluck($role, 'title')[0];

        if ($title == 'Admin') {
            abort_if(Gate::denies('admin_roles_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        }

        $role = Role::create([
            'title' => $request->title,
            'global_role' => 0,
            'created_by' => $user->id,
        ]);
        $role->permissions()->sync($request->input('permissions', []));
        $role->save();
        return response()->json(['message' => "success", 'status' => 200]);
    }

    public function show($role)
    {
        $role = Role::with('permissions')->where('id', $role)->get();
        return $role;
    }

    public function update(Request $request, $roleId)
    {
        $role = Role::where('id', $roleId)->first();
        $role->update($request->all());
        $role->permissions()->sync($request->input('permissions', []));
        $role->save();
        return response()->json(['message' => "success", 'status' => 200]);
    }

    public function globalRoles()
    {
        $user = Auth::user()->id;
        $roles = Role::select('id', 'title')->where('created_by', $user)->orWhere('global_role', 1)->get();
        return $roles;

    }

    public function delete($id)
    {
        $role_user = RoleUser::where('role_id', $id)->first();
        if ($role_user) {
            return response()->json([
                'status' => 400,
                'message' => 'Role is Assigned to a user'
            ]);
        } else {
            $role = Role::where('id', $id)->first();
            $role->permissions()->detach();
            $role->delete();
            return response()->json(['message' => "Role Deleted Successfully", 'status' => 200]);
        }
    }
}
