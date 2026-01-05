<?php

return [

    'wifi_status' => [
        'offline' => 0,
        'online' => 1,
    ],
    'roles' => [
        'admin' => 0,
        'location_owner' => 1,
        'customer' => 2,
        'distributor' => 3,
    ],
    'pdo_types' => [
        'lease' => 0,
        'outright' => 1,
    ],
    'pdo_commissions' => [
        0 => 30,
        1 => 80,
    ],
    'plan_duration' => [
        'One time' => false,
        'Monthly' => 1,
        'Quarterly' => 3,
        'Half Yearly' => 6,
        'Yearly' => 12
    ]
];
