<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder2 extends Seeder
{
    public function run()
    {
        $permissions = [
            [
                'id' => 87,
                'title' => 'admin_idpr_logs_access',
                'group' => 'Idpr'
            ],
            [
                'id' => 88,
                'title' => 'admin_payments_access',
                'group' => 'Payments'
            ],
            [
                'id' => 89,
                'title' => 'admin_settings_access',
                'group' => 'Settings'
            ],
        ];

        Permission::insert($permissions);

    }
}
