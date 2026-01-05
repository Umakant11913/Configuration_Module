<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder5 extends Seeder
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
                'id' => 93,
                'title' => 'admin_enable_auto-renew',
                'group' => 'Auto-Renew-Subscription'
            ],
            [
                'id' => 94,
                'title' => 'admin_disable_auto-renew',
                'group' => 'Auto-Renew-Subscription'
            ], [
                'id' => 95,
                'title' => 'pdo_enable_auto-renew',
                'group' => 'Auto-Renew-Subscription'
            ], [
                'id' => 96,
                'title' => 'pdo_disable_auto-renew',
                'group' => 'Auto-Renew-Subscription'
            ], [
                'id' => 97,
                'title' => 'admin_add_credits_sms',
                'group' => 'Admin'
            ], [
                'id' => 98,
                'title' => 'admin_routers-ap_activate',
                'group' => 'Admin'
            ], [
                'id' => 99,
                'title' => 'admin_routers-ap_deactivate',
                'group' => 'Admin'
            ], [
                'id' => 100,
                'title' => 'pdo_routers-ap_activate',
                'group' => 'PDO'
            ], [
                'id' => 101,
                'title' => 'pdo_routers-ap_deactivate',
                'group' => 'PDO'
            ], [
                'id' => 102,
                'title' => 'pdo_notification_settings',
                'group' => 'PDO'
            ],
            [
                'id' => 103,
                'title' => 'pdo_subscription_details_access',
                'group' => 'Subscription'
            ],
            [
                'id' => 104,
                'title' => 'pdo_subscription_history_access',
                'group' => 'Subscription'
            ],
        ];

        Permission::insert($permissions);
    }
}
