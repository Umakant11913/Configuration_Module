<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder6 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            [
                'id' => 105,
                'title' => 'pdo_accounts_access',
                'group' => 'Users'
            ],
            [
                'id' => 106,
                'title' => 'pdo_voucher_access',
                'group' => 'Zones'
            ], [
                'id' => 107,
                'title' => 'pdo_voucher_create',
                'group' => 'Zones'
            ], [
                'id' => 108,
                'title' => 'pdo_settings_access',
                'group' => 'Home'
            ], [
                'id' => 109,
                'title' => 'pdo_settings_submit_access',
                'group' => 'Home'
            ],
        ];

        Permission::insert($permissions);
    }
}
