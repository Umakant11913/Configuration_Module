<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder4 extends Seeder
{
    public function run()
    {
        $permissions = [
            [
                'id' => 92,
                'title' => 'pdo_zones_access',
                'group' => 'Zones'
            ],
        ];

        Permission::insert($permissions);

    }
}
