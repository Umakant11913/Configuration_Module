<?php

namespace App\Http\Controllers;

use App\Models\PaymentOrders;
use App\Models\InternetPlan;
use Illuminate\Http\Request;

class PaymentOrdersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $planId = $request->planId;
        $phone = $request->phone;
        $mac_address = $request->mac_address;
        $user_id = $request->user_id;
        $plan = InternetPlan::where('id', $planId)->first();
        $price = $plan->price;

        $order = array();

        $order['internet_plan_id'] = $planId;
        $order['wifi_user_id'] = $user_id;
        $order['franchise_id'] = 1;
        $order['amount'] = $price * 100;
        $order['status'] = 0;
        // $order['mac_address'] = $mac_address;

        $paymentOrder = PaymentOrders::create($order);

        return response()->json([
            'status' => true,
            'message' => 'Order created',
            'request' => $request->all(),
            'order' => $paymentOrder
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\PaymentOrders $paymentOrders
     * @return \Illuminate\Http\Response
     */
    public function show(PaymentOrders $paymentOrders)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\PaymentOrders $paymentOrders
     * @return \Illuminate\Http\Response
     */
    public function edit(PaymentOrders $paymentOrders)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\PaymentOrders $paymentOrders
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, PaymentOrders $paymentOrders)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\PaymentOrders $paymentOrders
     * @return \Illuminate\Http\Response
     */
    public function destroy(PaymentOrders $paymentOrders)
    {
        //
    }
}
