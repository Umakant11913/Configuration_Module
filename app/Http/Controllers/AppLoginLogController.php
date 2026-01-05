<?php

namespace App\Http\Controllers;

use App\Models\AppLoginLog;
use Illuminate\Http\Request;
use DB;

class AppLoginLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$app_login_log = AppLoginLog::select('username','app_id')->selectRaw('created_at')->orderBy('created_at', 'c')->get();
    	//return $app_login_log;

        $app_login_log = DB::select("SELECT * FROM app_login_logs order by created_at DESC");
        return $app_login_log;

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
     * @param  \App\Models\AppLoginLog  $appLoginLog
     * @return \Illuminate\Http\Response
     */
    public function show(AppLoginLog $appLoginLog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AppLoginLog  $appLoginLog
     * @return \Illuminate\Http\Response
     */
    public function edit(AppLoginLog $appLoginLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AppLoginLog  $appLoginLog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AppLoginLog $appLoginLog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AppLoginLog  $appLoginLog
     * @return \Illuminate\Http\Response
     */
    public function destroy(AppLoginLog $appLoginLog)
    {
        //
    }
}
