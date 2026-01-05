<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionRoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminPermission = Permission::where('title', 'like', 'admin_%')->get();
        Role::findorfail(1)->permissions()->sync($adminPermission->pluck('id'));

        $pdoPermission = Permission::where('title', 'like', 'pdo_%')->get();
        Role::findorfail(2)->permissions()->sync($pdoPermission->pluck('id'));

        $distributorPermission = Permission::where('title', 'like', 'distributor_%')->get();
        Role::findorfail(3)->permissions()->sync($distributorPermission->pluck('id'));
    }
}
