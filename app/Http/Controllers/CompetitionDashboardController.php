<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\PdoaRegistry;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompetitionDashboardController extends Controller
{
    public function competitiveDashboard(Request $request)
    {

        $state = $request->selectedState;
        $pdoa = $request->selectedPdoa;

        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        $startDateTimestamp = $startDate / 1000;
        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $endDateTimestamp = $endDate / 1000;
        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $mapCoordinates = [];


        if ($state && $pdoa) {

            $pdoRegistries = DB::table('pdoa_router_registries')
                ->leftJoin('pdoa_registries', 'pdoa_router_registries.pdoa_registry_id', '=', 'pdoa_registries.id')
                ->select('pdoa_registries.*', 'pdoa_registries.name as provider_name', 'pdoa_registries.created_at as createdAt', 'pdoa_router_registries.*', 'pdoa_router_registries.name as router_name')
                ->whereDate('pdoa_router_registries.created_at', '>=', $formattedStartDate)
                ->whereDate('pdoa_router_registries.created_at', '<=', $formattedEndDate)
                ->where('pdoa_router_registries.state', $state)
                ->where('pdoa_registries.name', $pdoa)
                ->get();

        } elseif ($state != null || $pdoa != null) {

            $pdoRegistries = DB::table('pdoa_router_registries')
                ->leftJoin('pdoa_registries', 'pdoa_router_registries.pdoa_registry_id', '=', 'pdoa_registries.id')
                ->select('pdoa_registries.*', 'pdoa_registries.name as provider_name', 'pdoa_registries.created_at as createdAt', 'pdoa_router_registries.*', 'pdoa_router_registries.name as router_name')
                ->whereDate('pdoa_router_registries.created_at', '>=', $formattedStartDate)
                ->whereDate('pdoa_router_registries.created_at', '<=', $formattedEndDate)
                ->where('pdoa_router_registries.state', $state)
                ->orWhere('pdoa_registries.name', $pdoa)
                ->get();

        } else {

            $pdoRegistries = DB::table('pdoa_router_registries')
                ->leftJoin('pdoa_registries', 'pdoa_router_registries.pdoa_registry_id', '=', 'pdoa_registries.id')
                ->select('pdoa_registries.*', 'pdoa_registries.name as provider_name', 'pdoa_registries.created_at as createdAt', 'pdoa_router_registries.*', 'pdoa_router_registries.name as router_name')
                ->whereDate('pdoa_router_registries.created_at', '>=', $formattedStartDate)
                ->whereDate('pdoa_router_registries.created_at', '<=', $formattedEndDate)
                ->get();

        }

        foreach ($pdoRegistries as $router) {
            $coordinates = explode(',', $router->geoLoc);

            if (count($coordinates) === 2) {
                $mapCoordinates[] = [
                    'lat' => $coordinates[0],
                    'lng' => $coordinates[1],
                    'status' => $router->status,
                    'state' => $router->state,
                    'name' => $router->provider_name,
                    'city' => $router->router_name,
                    'macid' => $router->macid,
                    'created_at' => Carbon::parse($router->created_at)->format('Y-m-d'),
                ];
            }
        }
        return [
            'mapCoordinates' => $mapCoordinates,
        ];
    }

    public function filterData(Request $request)
    {
        $uniquePdoa = PdoaRegistry::select('name')->distinct()->pluck('name')->toArray();

        return $uniquePdoa;
    }

    public function topPDOAS(Request $request)
    {
        $startDate = $request->startDate;
        $endDate = $request->endDate;

        $startimestamp = $startDate / 1000;
        $date = new DateTime("@$startimestamp");
        $startDate = $date->format('Y-m-d');

        $endtimestamp = $endDate / 1000;
        $date = new DateTime("@$endtimestamp");
        $endDate = $date->format('Y-m-d');

        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        $totalRecords = '';

        $topPdoas = DB::table('pdoa_router_registries AS prr')
            ->leftJoin('pdoa_registries AS pr', 'prr.pdoa_registry_id', '=', 'pr.id')
            ->select('pr.name', DB::raw('COUNT(prr.pdoa_registry_id) AS ap_count'))
            ->groupBy('pr.id', 'pr.name')->orderByDesc('ap_count')
            ->whereDate('prr.created_at', '>=', $startDate)
            ->whereDate('prr.created_at', '<=', $endDate)
            ->having('ap_count', '>', 0);

        $totalRecords = $topPdoas->count();
        $totalRecordswithFilter = $topPdoas->count();

        $data_arr = array();

        $topPdoas = $topPdoas->take($rowperpage)
            ->skip($start)
            ->get();

        foreach ($topPdoas as $topPdoa) {
            $data_arr[] = array(
                'name' => $topPdoa->name,
                'ap_count' => $topPdoa->ap_count,
            );
        }
        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "data" => $data_arr
        );

        return response()->json($response);
    }

    public function chartData(Request $request)
    {
        $days = 30;

        $state = $request->selectedState;
        $pdoa = $request->selectedPdoa;

        $currentDate = Carbon::today();
        $previousDate = Carbon::today()->subDays($days);

        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        $startDateTimestamp = $startDate / 1000;
        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $endDateTimestamp = $endDate / 1000;
        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $pdoRegistries = [];

        $total = 0;
        $grouped = [];

        $countMinDate = '';
        $countMaxDate = '';

        $startMonth = $previousDate->clone();
        $endMonth = $currentDate->clone();

        if ($state && $pdoa) {

            $pdoRegistries['chart_data'] = DB::table('pdoa_router_registries')
                ->leftJoin('pdoa_registries', 'pdoa_router_registries.pdoa_registry_id', '=', 'pdoa_registries.id')
                ->select(DB::raw('DATE(pdoa_router_registries.created_at) as date'))
                ->where('pdoa_router_registries.state', $state)
                ->where('pdoa_registries.name', $pdoa)
                ->whereBetween('pdoa_router_registries.created_at', [$formattedStartDate, $formattedEndDate])
                ->get();

            $countMinDate = strtotime($formattedStartDate) * 1000;
            $countMaxDate = strtotime($formattedEndDate) * 1000;
            $grouped = $pdoRegistries['chart_data']->groupBy('date');
            $data = [];

            for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
                $key = $i->format('Y-m-d');
                $temp = [$key, 0];
                if ($grouped->has($key)) {
                    $temp[1] = $grouped->get($key)->count();
                }
                $data[] = $temp;
            }

        } elseif ($state != null || $pdoa != null) {

            $pdoRegistries['chart_data'] = DB::table('pdoa_router_registries')
                ->leftJoin('pdoa_registries', 'pdoa_router_registries.pdoa_registry_id', '=', 'pdoa_registries.id')
                ->select(DB::raw('DATE(pdoa_router_registries.created_at) as date'))
                ->where('pdoa_router_registries.state', $state)
                ->orWhere('pdoa_registries.name', $pdoa)
                ->whereBetween('pdoa_router_registries.created_at', [$formattedStartDate, $formattedEndDate])
                ->get();

            $countMinDate = strtotime($formattedStartDate) * 1000;
            $countMaxDate = strtotime($formattedEndDate) * 1000;
            $grouped = $pdoRegistries['chart_data']->groupBy('date');
            $data = [];

            for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
                $key = $i->format('Y-m-d');
                $temp = [$key, 0];
                if ($grouped->has($key)) {
                    $temp[1] = $grouped->get($key)->count();
                }
                $data[] = $temp;
            }

        } else {

            $pdoRegistries['chart_data'] = DB::table('pdoa_router_registries')
                ->leftJoin('pdoa_registries', 'pdoa_router_registries.pdoa_registry_id', '=', 'pdoa_registries.id')
                ->select(DB::raw('DATE(pdoa_router_registries.created_at) as date'))
                ->whereBetween('pdoa_router_registries.created_at', [$formattedStartDate, $formattedEndDate])
                ->get();

            $countMinDate = strtotime($formattedStartDate) * 1000;
            $countMaxDate = strtotime($formattedEndDate) * 1000;
            $grouped = $pdoRegistries['chart_data']->groupBy('date');
            $data = [];

            for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
                $key = $i->format('Y-m-d');
                $temp = [$key, 0];
                if ($grouped->has($key)) {
                    $temp[1] = $grouped->get($key)->count();
                }
                $data[] = $temp;
            }
        }
        return [
            'chart_data' => $data,
            'xMin' => $countMinDate,
            'xMax' => $countMaxDate,
        ];
    }
}
