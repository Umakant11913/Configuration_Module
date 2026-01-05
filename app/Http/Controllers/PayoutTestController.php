<?php

namespace App\Http\Controllers;

use App\Jobs\UpdatePayableToZohoBook;
use App\Models\User;
use Illuminate\Http\Request;

class PayoutTestController extends Controller
{
    public function test()
    {
        $users = User::query()
            ->locationOwners()
            ->limit(2)
            ->get();

        $commissions = [];
        foreach ($users as $user) {
            $data = [];
            $data['user_id'] = $user->id;
            $data['percentage'] = 20;
            $data['reference'] = 'random details for this commission';
            $commissions[] = $data;
        }
        
        $total_amount = 1000;

        try {
            UpdatePayableToZohoBook::dispatch($commissions, $total_amount);
        } catch (\Exception $e) {
            dd($e);
        }
        return 'success';
    }
}
