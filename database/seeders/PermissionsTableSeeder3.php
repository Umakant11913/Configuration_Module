<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder3 extends Seeder
{
    public function run()
    {
        $permissions = [
            [
                'id' => 90,
                'title' => 'pdo_accounts_access',
                'group' => 'Users'
            ],
            [
                'id' => 91,
                'title' => 'pdo_payments_access',
                'group' => 'Users'
            ],
        ];

        Permission::insert($permissions);

    }
}
