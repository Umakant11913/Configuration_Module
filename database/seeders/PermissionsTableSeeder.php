<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            [
                'id' => 1,
                'title' => 'admin_model_create',
                'group' => 'Home'
            ],
            [
                'id' => 2,
                'title' => 'admin_model_access',
                'group' => 'Home'
            ],
            [
                'id' => 3,
                'title' => 'admin_model_firmware-upload',
                'group' => 'Home'
            ],
            [
                'id' => 4,
                'title' => 'admin_model_access',
                'group' => 'Home'
            ],
            [
                'id' => 5,
                'title' => 'admin_inventory_access',
                'group' => 'Home'
            ],
            [
                'id' => 6,
                'title' => 'admin_inventory_create',
                'group' => 'Home'
            ],
            [
                'id' => 7,
                'title' => 'admin_inventory_edit',
                'group' => 'Home'
            ],
            [
                'id' => 8,
                'title' => 'admin_inventory_access',
                'group' => 'Home'
            ],
            [
                'id' => 9,
                'title' => 'admin_inventory_assign',
                'group' => 'Home'
            ],
            [
                'id' => 10,
                'title' => 'admin_network-settings_access',
                'group' => 'Home'
            ],
            [
                'id' => 11,
                'title' => 'admin_plans_access',
                'group' => 'Users'
            ],
            [
                'id' => 12,
                'title' => 'admin_plans_create',
                'group' => 'Users'
            ],
            [
                'id' => 13,
                'title' => 'admin_plans_update',
                'group' => 'Users'
            ],
            [
                'id' => 14,
                'title' => 'admin_accounts_access',
                'group' => 'Users'
            ],
            [
                'id' => 15,
                'title' => 'admin_sessions_access',
                'group' => 'Users'
            ],
            [
                'id' => 16,
                'title' => 'admin_payments_access',
                'group' => 'Users'
            ],
            [
                'id' => 17,
                'title' => 'admin_roles_create',
                'group' => 'Team'
            ],
            [
                'id' => 18,
                'title' => 'admin_roles_access',
                'group' => 'Team'
            ],
            [
                'id' => 19,
                'title' => 'admin_plans_access',
                'group' => 'PDO'
            ],
            [
                'id' => 20,
                'title' => 'admin_plans_create',
                'group' => 'PDO'
            ],
            [
                'id' => 21,
                'title' => 'admin_plans_update',
                'group' => 'PDO'
            ],
            [
                'id' => 22,
                'title' => 'admin_accounts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 23,
                'title' => 'admin_accounts_create',
                'group' => 'PDO'
            ],
            [
                'id' => 24,
                'title' => 'admin_accounts_edit',
                'group' => 'PDO'
            ],
            [
                'id' => 25,
                'title' => 'admin_locations_access',
                'group' => 'PDO'
            ],
            [
                'id' => 26,
                'title' => 'admin_locations_create',
                'group' => 'PDO'
            ],
            [
                'id' => 27,
                'title' => 'admin_locations_edit',
                'group' => 'PDO'
            ],
            [
                'id' => 28,
                'title' => 'admin_payouts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 29,
                'title' => 'admin_payouts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 30,
                'title' => 'admin_requests_access',
                'group' => 'PDO'
            ],
            [
                'id' => 31,
                'title' => 'admin_plans_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 32,
                'title' => 'admin_plans_create',
                'group' => 'Distributor'
            ],
            [
                'id' => 33,
                'title' => 'admin_plans_update',
                'group' => 'Distributor'
            ],
            [
                'id' => 34,
                'title' => 'admin_accounts_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 35,
                'title' => 'admin_accounts_create',
                'group' => 'Distributor'
            ],
            [
                'id' => 36,
                'title' => 'admin_accounts_update',
                'group' => 'Distributor'
            ],
            [
                'id' => 37,
                'title' => 'admin_payouts_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 38,
                'title' => 'admin_payouts_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 39,
                'title' => 'pdo_inventory_access',
                'group' => 'Home'
            ],
            [
                'id' => 40,
                'title' => 'pdo_inventory_edit',
                'group' => 'Home'
            ],
            [
                'id' => 41,
                'title' => 'pdo_monitor_access',
                'group' => 'Home'
            ],
            [
                'id' => 42,
                'title' => 'pdo_plans_access',
                'group' => 'Users'
            ],
            [
                'id' => 43,
                'title' => 'pdo_sessions_access',
                'group' => 'Users'
            ],
            [
                'id' => 44,
                'title' => 'pdo_location_access',
                'group' => 'PDO'
            ],
            [
                'id' => 45,
                'title' => 'pdo_location_create',
                'group' => 'PDO'
            ],
            [
                'id' => 46,
                'title' => 'pdo_location_edit',
                'group' => 'PDO'
            ],
            [
                'id' => 47,
                'title' => 'pdo_payouts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 48,
                'title' => 'pdo_payouts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 49,
                'title' => 'distributor_inventory_access',
                'group' => 'Home'
            ],
            [
                'id' => 50,
                'title' => 'distributor_inventory_edit',
                'group' => 'Home'
            ],
            [
                'id' => 51,
                'title' => 'distributor_inventory_access',
                'group' => 'Home'
            ],
            [
                'id' => 52,
                'title' => 'distributor_monitor_access',
                'group' => 'Home'
            ],
            [
                'id' => 53,
                'title' => 'distributor_sessions_access',
                'group' => 'Users'
            ],
            [
                'id' => 54,
                'title' => 'distributor_payments_access',
                'group' => 'Users'
            ],
            [
                'id' => 55,
                'title' => 'distributor_accounts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 56,
                'title' => 'distributor_accounts_create',
                'group' => 'PDO'
            ],
            [
                'id' => 57,
                'title' => 'distributor_accounts_edit',
                'group' => 'PDO'
            ],
            [
                'id' => 58,
                'title' => 'distributor_locations_access',
                'group' => 'PDO'
            ],
            [
                'id' => 59,
                'title' => 'distributor_locations_create',
                'group' => 'PDO'
            ],
            [
                'id' => 60,
                'title' => 'distributor_locations_edit',
                'group' => 'PDO'
            ],
            [
                'id' => 61,
                'title' => 'distributor_account_create',
                'group' => 'Distributor'
            ],
            [
                'id' => 62,
                'title' => 'distributor_account_edit',
                'group' => 'Distributor'
            ],
            [
                'id' => 63,
                'title' => 'distributor_account_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 64,
                'title' => 'distributor_payout_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 65,
                'title' => 'distributor_payout_access',
                'group' => 'Distributor'
            ],
            [
                'id' => 66,
                'title' => 'admin_monitor_access',
                'group' => 'Home'
            ],
            [
                'id' => 67,
                'title' => 'pdo_roles_access',
                'group' => 'Team'
            ],
            [
                'id' => 68,
                'title' => 'distributor_roles_access',
                'group' => 'Team'
            ],
            [
                'id' => 69,
                'title' => 'admin_modify_network_settings',
                'group' => 'Home'
            ],
            [
                'id' => 70,
                'title' => 'distributor_payouts_access',
                'group' => 'PDO'
            ],
            [
                'id' => 71,
                'title' => 'pdo_plans_create',
                'group' => 'Zones'
            ],
            [
                'id' => 72,
                'title' => 'pdo_plans_access',
                'group' => 'Zones'
            ],
            [
                'id' => 73,
                'title' => 'pdo_plans_update',
                'group' => 'Zones'
            ],
            [
                'id' => 74,
                'title' => 'pdo_zone_access',
                'group' => 'Zones'
            ],
            [
                'id' => 75,
                'title' => 'pdo_zone_create',
                'group' => 'Zones'
            ],
            [
                'id' => 76,
                'title' => 'admin_monitor_edit',
                'group' => 'Home'
            ],
            [
                'id' => 77,
                'title' => 'admin_team_access',
                'group' => 'Team'
            ],
            [
                'id' => 78,
                'title' => 'pdo_team_access',
                'group' => 'Team'
            ],
            [
                'id' => 79,
                'title' => 'distributor_team_access',
                'group' => 'Team'
            ],
            [
                'id' => 80,
                'title' => 'pdo_roles_create',
                'group' => 'Team'
            ],
            [
                'id' => 81,
                'title' => 'distributor_roles_create',
                'group' => 'Team'
            ],
            [
                'id' => 82,
                'title' => 'admin_invite_team-member',
                'group' => 'Team'
            ],
            [
                'id' => 83,
                'title' => 'pdo_invite_team-member',
                'group' => 'Team'
            ],
            [
                'id' => 84,
                'title' => 'distributor_invite_team-member',
                'group' => 'Team'
            ],
            [
                'id' => 85,
                'title' => 'admin_idpr_logs_access',
                'group' => 'Idpr'
            ],
            [
                'id' => 86,
                'title' => 'admin_payments_access',
                'group' => 'Payments'
            ],
            [
                'id' => 87,
                'title' => 'admin_settings_access',
                'group' => 'Settings'
            ],
        ];

        Permission::insert($permissions);

    }
}
