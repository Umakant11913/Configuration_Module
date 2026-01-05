<?php

namespace App\Http\Controllers;

use App\Models\Payments;
use App\Models\PaymentOrders;

use Illuminate\Http\Request;

class PaymentsController extends Controller
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
        $order = PaymentOrders::where('id', $request->order_id);
        if ($order) {
            $payment = array();
            $payment['wifi_user_id'] = $order->wifi_user_id;
            $payment['franchise_id'] = $order->franchise_id;
            $payment['internet_plan_id'] = $order->internet_plan_id;
            $payment['payment_method'] = 'Razorpay';
            $payment['payment_status'] = 'payment-received';
            $payment['amount'] = $request->amount;
            $payment['payment_reference_id'] = $request->payment_id;
            $payment_record = Payments::create($payment);

            return response()->json([
                'status' => true,
                'message' => 'Order successfully placed',
                'payment' => $payment_record
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Order does not exists',
            ], 401);
        }
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
     * @param \App\Models\Payments $payments
     * @return \Illuminate\Http\Response
     */
    public function show(Payments $payments)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Payments $payments
     * @return \Illuminate\Http\Response
     */
    public function edit(Payments $payments)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Payments $payments
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Payments $payments)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Payments $payments
     * @return \Illuminate\Http\Response
     */
    public function destroy(Payments $payments)
    {
        //
    }
}
