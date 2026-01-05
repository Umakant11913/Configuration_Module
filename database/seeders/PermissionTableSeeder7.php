<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionTableSeeder7 extends Seeder
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
                'id' => 110,
                'title' => 'pdo_allvouchers_access',
                'group' => 'Zones'
            ],
            [
                'id' => 111,
                'title' => 'pdo_ap-settings_access',
                'group' => 'Settings'
            ], [
                'id' => 112,
                'title' => 'pdo_notification_access',
                'group' => 'Settings'
            ], [
                'id' => 113,
                'title' => 'pdo_global-payment_access',
                'group' => 'Settings'
            ], [
                'id' => 114,
                'title' => 'pdo_ap-settings_submit_access',
                'group' => 'Settings'
            ]
        ];

        Permission::insert($permissions);
    }
}
