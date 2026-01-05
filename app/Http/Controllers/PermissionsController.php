<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use function Symfony\Component\String\u;

class PermissionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['index', 'store']]);
    }

    public function index()
    {
        $permissions = Permission::all();
        return $permissions;
    }

    public function store(Request $request)
    {
        $data = $request->name;
        $permission = Permission::create([
            'title' => $data
        ]);
        $permission->save();
        return response()->json(['message' => "success", 'status' => 200]);
    }

    public function permissionList($user)
    {
        $user = User::with('roles')->where('id', $user)->first();
        $roles = $user->roles;
        $role_id = $roles->pluck('id');
        $role = Role::with('permissions')->whereIn('id', $role_id)->get();
        $permissions = $role->pluck('permissions');
        return $permissions;
    }

    public function currentUser()
    {
        $user = Auth::user();
        $role = $user->role;
        // Admin
        if ($role === 0) {
            $allPermissions = Permission::where('title', 'like', 'admin_%')->select('id', 'title', 'group')->orderBy('group')->get();
        }
        //PDO
        if ($role === 1) {
            $allPermissions = Permission::where('title', 'like', 'pdo_%')->select('id', 'title', 'group')->orderBy('group')->get();
        }
        //Distributor
        if ($role === 3) {
            $allPermissions = Permission::where('title', 'like', 'distributor_%')->select('id', 'title', 'group')->orderBy('group')->get();
        }
        $allPermissionsMap = [];
        $uniqueActions = $uniqueRoles = [];
        foreach ($allPermissions as $permission) {
            $permissionParts = explode('_', $permission->title);

            if (!isset($allPermissionsMap[$permission->group])) {
                $allPermissionsMap[$permission->group] = [];
                $uniqueActions = [];
            }

            if (!isset($allPermissionsMap[$permission->group]['entity_list'])) {
                $allPermissionsMap[$permission->group]['entity_list'] = [];
                $uniqueRoles = array_unique(array_merge($uniqueRoles, [$permissionParts[1]]));
            }

            $uniqueActions = array_unique(array_merge($uniqueActions, [$permissionParts[2]]));

            $allPermissionsMap[$permission->group]['entity_list'][$permissionParts[1]][] = [
                'permission_name' => $permissionParts[2],
                'permission_id' => $permission->id,
            ];

            $allPermissionsMap[$permission->group]['unique_actions'] = $uniqueActions;
        }
        return [
            'permissions' => $allPermissionsMap
        ];


    }
}
