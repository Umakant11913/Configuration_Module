<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder9 extends Seeder

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
                'id' => 120,
                'title' => 'admin_idpr_logs_access',
                'group' => 'Admin'
            ],
            [
                'id' => 121,
                'title' => 'admin_settings_access',
                'group' => 'Admin'
            ],
            [
                'id' => 122,
                'title' => 'admin_team_access',
                'group' => 'Admin'
            ],
            [
                'id' => 123,
                'title' => 'distributor_team_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 124,
                'title' => 'pdo_team_access',
                'group' => 'PDO'
            ],
            [
                'id' => 125,
                'title' => 'admin_invite_team-member',
                'group' => 'Admin'
            ],
            [
                'id' => 126,
                'title' => 'distributor_invite_team-member',
                'group' => 'Distributor'
            ],
            [
                'id' => 127,
                'title' => 'pdo_invite_team-member',
                'group' => 'PDO'
            ],
            [
                'id' => 128,
                'title' => 'pdo_roles_create',
                'group' => 'PDO'
            ],
            [
                'id' => 129,
                'title' => 'distributor_roles_create',
                'group' => 'Distributor'
            ],
            [
                'id' => 130,
                'title' => 'admin_enable_auto-renew',
                'group' => 'Admin'
            ],
            [
                'id' => 131,
                'title' => 'admin_disable_auto-renew',
                'group' => 'Admin'
            ],
            [
                'id' => 132,
                'title' => 'pdo_enable_auto-renew',
                'group' => 'PDO'
            ],
            [
                'id' => 133,
                'title' => 'pdo_disable_auto-renew',
                'group' => 'PDO'
            ],
            [
                'id' => 134,
                'title' => 'admin_add_credits_sms',
                'group' => 'Admin'
            ],
            [
                'id' => 135,
                'title' => 'admin_enable_auto-renew',
                'group' => 'Admin'
            ],
            [
                'id' => 136,
                'title' => 'admin_routers-ap_activate',
                'group' => 'Admin'
            ],
            [
                'id' => 137,
                'title' => 'admin_routers-ap_deactivate',
                'group' => 'Admin'
            ],
            [
                'id' => 138,
                'title' => 'pdo_routers-ap_activate',
                'group' => 'PDO'
            ],
            [
                'id' => 139,
                'title' => 'pdo_routers-ap_deactivate',
                'group' => 'PDO'
            ],
            [
                'id' => 140,
                'title' => 'pdo_notification_settings',
                'group' => 'PDO'
            ],
            [
                'id' => 141,
                'title' => 'pdo_subscription_details_access',
                'group' => 'PDO'
            ],
            [
                'id' => 142,
                'title' => 'pdo_subscription_history_access',
                'group' => 'PDO'
            ],
            [
                'id' => 143,
                'title' => 'pdo_accounts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 144,
                'title' => 'pdo_voucher_create',
                'group' => 'PDO'
            ],
            [
                'id' => 145,
                'title' => 'pdo_voucher_access',
                'group' => 'PDO'
            ],
            [
                'id' => 146,
                'title' => 'pdo_allvouchers_access',
                'group' => 'PDO'
            ],
            [
                'id' => 147,
                'title' => 'pdo_settings_access',
                'group' => 'PDO'
            ],
            [
                'id' => 148,
                'title' => 'pdo_settings_submit_access',
                'group' => 'PDO'
            ],
            [
                'id' => 149,
                'title' => 'pdo_ap-settings_access',
                'group' => 'PDO'
            ],
            [
                'id' => 150,
                'title' => 'pdo_notification_access',
                'group' => 'PDO'
            ],
            [
                'id' => 151,
                'title' => 'pdo_global-payment_access',
                'group' => 'PDO'
            ],
            [
                'id' => 152,
                'title' => 'pdo_ap-settings_submit_access',
                'group' => 'PDO'
            ],
            [
                'id' => 153,
                'title' => 'pdo_wifi_config_profiles_access',
                'group' => 'PDO'
            ],
            [
                'id' => 154,
                'title' => 'pdo_user_access_control_access',
                'group' => 'PDO'
            ],
            [
                'id' => 155,
                'title' => 'pdo_advertisement_access',
                'group' => 'PDO'
            ],
            [
                'id' => 156,
                'title' => 'pdo_advertisement_create',
                'group' => 'PDO'
            ],
            [
                'id' => 157,
                'title' => 'pdo_advertisement_update',
                'group' => 'PDO'
            ],
            [
                'id' => 158,
                'title' => 'pdo_advertisement_view',
                'group' => 'PDO'
            ],
            [
                'id' => 159,
                'title' => 'pdo_advertisement_delete',
                'group' => 'PDO'
            ],
            [
                'id' => 160,
                'title' => 'pdo_payouts_dashboard_access',
                'group' => 'PDO'
            ],



        ];
        Permission::insert($permissions);
    }
}
