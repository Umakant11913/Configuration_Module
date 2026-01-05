<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payout extends BaseModel
{

    public function order()
    {
        return $this->belongsTo(WiFiOrders::class, 'order_id');
    }

    public static function addCommission($owner, $order)
    {

        $commissionPercentage = $owner->pdoaPlan ? $owner->pdoaPlan->commission : 0;
//      $commissionPercentage = config('constants.pdo_commissions')[$owner->pdo_type ?? 0];
        $commission = round($order->amount * $commissionPercentage / 100, 2);
        $gst = round($commission * 0.18, 2);

        return Payout::create([
            'order_id' => $order->id,
            'owner_id' => $owner->id,
            'payout_amount' => $commission - $gst,
            'gst_amount' => $gst,
        ]);
    }

    public static function addAcquisitionCommission($order, $parent_id, $user_id)
    {

        $user = User::find($user_id);
        $owner = User::find($parent_id);
        if (!$user || !$owner) {
            return null;
        }
        $acquisition = UserAcquisition::where('user_id', $user->id)->where('owner_id', $owner->id)->first();

        if (!$acquisition) {
            return null;
        }

        $acquisitionMonths = $acquisition->acquisition_commission_duration;
        $commissionPercentage = $owner->pdoaPlan ? $owner->pdoaPlan->acquisition_commission : 0;

        if ($commissionPercentage == 0 || $acquisition->created_at->diffInMonths(Carbon::today() > $acquisitionMonths)) {
            return null;
        }

        $commission = round($order->amount * $commissionPercentage / 100, 2);
        $gst = round($commission * 0.18, 2);

        return Payout::create([
            'order_id' => $order->id,
            'owner_id' => $owner->id,
            'payout_amount' => $commission - $gst,
            'gst_amount' => $gst,
        ]);
    }
}
