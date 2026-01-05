<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $role = [
            [
                'id' => 1,
                'title' => 'Admin',
                'system_role' => 1
            ],
            [
                'id' => 2,
                'title' => 'PDO',
                'system_role' => 1
            ],
            [
                'id' => 3,
                'title' => 'Distributor',
                'system_role' => 1
            ],
        ];

        Role::insert($role);

    }
}
