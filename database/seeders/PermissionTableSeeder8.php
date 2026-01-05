<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionTableSeeder8 extends Seeder

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
                'id' => 115,
                'title' => 'pdo_advertisement_access',
                'group' => 'Advertisement'
            ],
            [
                'id' => 116,
                'title' => 'pdo_advertisement_create',
                'group' => 'Advertisement'
            ], [
                'id' => 117,
                'title' => 'pdo_advertisement_update',
                'group' => 'Advertisement'
            ], [
                'id' => 118,
                'title' => 'pdo_advertisement_view',
                'group' => 'Advertisement'
            ], [
                'id' => 119,
                'title' => 'pdo_advertisement_delete',
                'group' => 'Advertisement'
            ]
        ];
        Permission::insert($permissions);
    }
}
