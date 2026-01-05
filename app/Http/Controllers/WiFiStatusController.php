<?php

namespace App\Http\Controllers;

use App\Models\WiFiStatus;
use Illuminate\Http\Request;
use Validator;

class WiFiStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if(!isset($request->start) || !isset($request->end)) {
            $wifiRouterStatus = WiFiStatus::where('wifi_router_id',$id)->first();
            if(! $wifiUser){
                return response()->json([
                    'status' => 'failure',
                    'message' => 'WiFi user Does not exist',
                ], 201);
        }
        }else{
            return response()->json([
                'status' => 'success',
                'message' => 'WiFi user details retrived Successfully',
                'data' => $wifiUser
            ], 201);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }
    

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WiFiStatus  $wiFiStatus
     * @return \Illuminate\Http\Response
     */
    public function show(WiFiStatus $wiFiStatus)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WiFiStatus  $wiFiStatus
     * @return \Illuminate\Http\Response
     */
    public function edit(WiFiStatus $wiFiStatus)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WiFiStatus  $wiFiStatus
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, WiFiStatus $wiFiStatus)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WiFiStatus  $wiFiStatus
     * @return \Illuminate\Http\Response
     */
    public function destroy(WiFiStatus $wiFiStatus)
    {
        //
    }
}
