<?php

namespace App\Http\Controllers;

use App\Events\PdoSmsQuotaEvent;
use App\Events\PdoUsedSmsQuotaEvents;
use App\Models\BrandingProfile;
use App\Models\Location;
use App\Models\PdoaPlan;
use App\Models\PdoSmsQuota;
use App\Models\Router;
use App\Models\SmsHistory;
use App\Models\User;
use App\Models\Distributor;
use App\Models\WiFiOrders;
use App\Models\ZohoOrder;
use App\Models\PayoutLog;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use com\zoho\crm\api\notification\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use function PHPUnit\Framework\isEmpty;

use App\Exports\WifiStatusExport;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function apOnlineCount(Request $request)
    {
        $routerTotalCount = 0;
        $user = Auth::user();
        if ($user->isDistributor()) {
            $routerTotalCount = Router::where('distributor_id', $getId->id)->count();
        }
        if ($user->isPDO()) {
            
            $routerTotalCount = Router::with('location')->where('pdo_id', $user->id)->orWhereHas('location', function ($query) {
                $user = Auth::user();
                if ($user->parent_id) {
                    $parent = User::where('id', $user->parent_id)->first();
                    $user = $parent;
                }
                $query->where('owner_id', $user->id);
            })->count();
        }
        if ($user->isAdmin()) {
            $routerTotalCount = Router::count();
        }
        $routerOnlineCount = Router::mine()->online()->count();
        return compact('routerTotalCount', 'routerOnlineCount');
    }
    public function index(Request $request)
    {
        $days = 30;
        $pdoTotalAmount = 0;

        $currentDate = Carbon::today();
        $previousDate = Carbon::today()->subDays($days);
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');

        $locationId = $request->get('location');
        $zoneId = $request->get('zone');
        $zoneLocationIds = Location::where('profile_id', $zoneId)->get();

        $zoneIds = [];
        if ($zoneLocationIds->isNotEmpty()) {
            foreach ($zoneLocationIds as $zoneLocationId) {
                $zoneIds[] = $zoneLocationId->id;
            }
        } else {
            $zoneIds = [];
        }
        //  dd($zoneLocationId);

        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;
        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');
        $routers = [];

        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }

        if ($user->isDistributor()) {
            $getId = Distributor::where('owner_id', $user->id)->first();
            $total = Router::where('distributor_id', $getId->id)->count();
        }
        if ($user->isPDO()) {
            
            $total = Router::with('location')->where('pdo_id', $user->id)->orWhereHas('location', function ($query) {
                $user = Auth::user();
                if ($user->parent_id) {
                    $parent = User::where('id', $user->parent_id)->first();
                    $user = $parent;
                }
                $query->where('owner_id', $user->id);
            })->count();
            $locationsnew = Location::where('owner_id', Auth::user()->id)->get();

            /* if ($locationsnew->isNotEmpty()) {
                 foreach ($locationsnew as $locationpdo) {
                     $location_id = $locationpdo->id;
                     $location_name = $locationpdo->name;
                     $pdo_location [ ] =array(
                         'id' => $location_id,
                         'name' => $location_name,
                     );
                 }
             } else {
                 $pdo_location = [];
             }
             $zones = BrandingProfile::where('pdo_id',Auth::user()->id)->get();
             if ($zones->isNotEmpty()) {
                 foreach ($zones as $zone) {
                     $zone_id = $zone->id;
                     $zone_name = $zone->name;
                     $pdo_zone [ ] =array(
                         'id' => $zone_id,
                         'name' => $zone_name,
                     );
                 }
             } else {
                 $pdo_zone = [];
             }*/

        }
        if ($user->isAdmin()) {
            $total = Router::count();
        }

        $routers['total'] = $total;
        $routers['online'] = Router::mine()->online()->count();

        $locations = Location::mine()->pluck('id')->toArray();

        $payments = [];
        $payments['current_month'] = WiFiOrders::forLocations($locations)
            ->where('status', 1)
            ->forDays($currentDate)->sum('amount');
        $payments['previous_month'] = WiFiOrders::forLocations($locations)
            ->where('status', 1)
            ->forDays($previousDate)->sum('amount');

        $sessions = [];

        $monthQuery = function ($date, $column = 'acctstarttime', $days = 30) {
            $endDate = $date->clone();
            $startDate = $date->clone()->subDays($days);
            return function ($query) use ($startDate, $endDate, $column) {
                $query->whereDate($column, '>=', $startDate);
                $query->whereDate($column, '<=', $endDate);
                return $query;
            };
        };

        $yearQuery = function (Carbon $date, $column = 'created_at') {
            $startDate = $date->clone()->startOfYear();
            $endDate = $date->clone()->endOfYear();
            return function ($query) use ($startDate, $endDate, $column) {
                $query->whereDate($column, '>=', $startDate);
                $query->whereDate($column, '<=', $endDate);
                return $query;
            };
        };

        $sessions['current_month'] = DB::connection('mysql2')
            ->table('radacct')
            ->whereIn('location_id', $locations)
            ->where($monthQuery($currentDate))
            ->count();

        $sessions['previous_month'] = DB::connection('mysql2')
            ->table('radacct')
            ->whereIn('location_id', $locations)
            ->where($monthQuery($previousDate))
            ->count();


        $startMonth = $previousDate->clone();
        $endMonth = $currentDate->clone();

        $locations = Location::mine()->pluck('id')->toArray();
        if ($locationId || $zoneId) {
            $flattenedZoneIds = array_merge([$locationId], $zoneIds);
            $sessions['current_month_breakup'] = DB::connection('mysql2')
                ->table('radacct')
                ->orwhereIn('location_id', $flattenedZoneIds)
                ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('count(*) as sessions'))
                ->groupBy('date')
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('acctstarttime', '>=', $formattedStartDate)
                        ->whereDate('acctstarttime', '<=', $formattedEndDate);
                })
                ->get();

            $sessions['current_month'] = DB::connection('mysql2')
                ->table('radacct')
                ->orwhereIn('location_id', $flattenedZoneIds)
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('acctstarttime', '>=', $formattedStartDate)
                        ->whereDate('acctstarttime', '<=', $formattedEndDate);
                })
                ->count();
        } else {

            $sessions['current_month_breakup'] = DB::connection('mysql2')
                ->table('radacct')
                ->whereIn('location_id', $locations)
                ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('count(*) as sessions'))
                ->groupBy('date')
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('acctstarttime', '>=', $formattedStartDate)
                        ->whereDate('acctstarttime', '<=', $formattedEndDate);
                })
                ->get();

            $sessions['current_month'] = DB::connection('mysql2')
                ->table('radacct')
                ->whereIn('location_id', $locations)
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('acctstarttime', '>=', $formattedStartDate)
                        ->whereDate('acctstarttime', '<=', $formattedEndDate);
                })
                ->count();
        }
        $sessions_xmin = strtotime($formattedStartDate) * 1000;
        $sessions_xmax = strtotime($formattedEndDate) * 1000;

        $grouped = $sessions['current_month_breakup']->groupBy('date');
        $data = [];
        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');
            $temp = [$key, 0];
            if ($grouped[$key] ?? false) {
                $temp[1] = $grouped[$key][0]->sessions ?? 0;
            }
            $data[] = $temp;
        }
        $sessions['current_month_breakup'] = $data;

        $user_orders = [];

        if ($user->isAdmin()) {

            $user_orders['paid'] = DB::table('wi_fi_orders')
                ->whereIn('location_id', $locations)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as activations'))
                ->where('status', 1)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [$formattedStartDate, $formattedEndDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            $user_orders['free'] = DB::table('wi_fi_orders')
                ->whereIn('location_id', $locations)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as activations'))
                ->where('status', 1)
                ->where('payment_status', 'free')
                ->whereBetween('created_at', [$formattedStartDate, $formattedEndDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            $user_orders['cancelled'] = DB::table('wi_fi_orders')
                ->whereIn('location_id', $locations)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as activations'))
                ->where('status', 0)
                ->where('payment_status', 'cancelled')
                ->whereBetween('created_at', [$formattedStartDate, $formattedEndDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            $user_orders['failed'] = DB::table('wi_fi_orders')
                ->whereIn('location_id', $locations)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as activations'))
                ->where('status', 0)
                ->where('payment_status', 'failed')
                ->whereBetween('created_at', [$formattedStartDate, $formattedEndDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $user_orders['total'] = WiFiOrders::forLocations($locations)
                ->where(function ($query) {
                    $query->where('status', 1)
                        ->orWhere('status', 0);
                })
                ->where(function ($query) {
                    $query->where('payment_status', 'paid')
                        ->orWhere('payment_status', 'free')
                        ->orWhere('payment_status', 'cancelled')
                        ->orWhere('payment_status', 'failed');
                })
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
            $user_orders['total_paid'] = WiFiOrders::forLocations($locations)
                ->where('status', 1)
                ->where('payment_status', 'paid')
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
            $user_orders['total_free'] = WiFiOrders::forLocations($locations)
                ->where('status', 1)
                ->where('payment_status', 'free')
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
            $user_orders['total_cancelled'] = WiFiOrders::forLocations($locations)
                ->where('status', 0)
                ->where('payment_status', 'cancelled')
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
            $user_orders['total_failed'] = WiFiOrders::forLocations($locations)
                ->where('status', 0)
                ->where('payment_status', 'failed')
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
        }

        if (Auth::user()->isPDO()) {
            if ($locationId || $zoneId) {
                $flattenedZoneIds = array_merge([$locationId], $zoneIds);
                $user_orders['paid'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->orWhereIn('wi_fi_orders.location_id', $flattenedZoneIds)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 1)
                    ->where('wi_fi_orders.payment_status', 'paid')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                // dd($locationZoneIds);
                $user_orders['free'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->orWhereIn('wi_fi_orders.location_id', $flattenedZoneIds)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 1)
                    ->where('wi_fi_orders.payment_status', 'free')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                $user_orders['cancelled'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->orWhereIn('wi_fi_orders.location_id', $flattenedZoneIds)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'cancelled')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                $user_orders['failed'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->orWhereIn('wi_fi_orders.location_id', $flattenedZoneIds)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'failed')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                $user_orders['total'] = WiFiOrders::forLocations($flattenedZoneIds)
                    ->where(function ($query) {
                        $query->where('wi_fi_orders.status', 1)
                            ->orWhere('wi_fi_orders.status', 0);
                    })
                    ->where(function ($query) {
                        $query->where('wi_fi_orders.payment_status', 'paid')
                            ->orWhere('wi_fi_orders.payment_status', 'free')
                            ->orWhere('wi_fi_orders.payment_status', 'cancelled')
                            ->orWhere('wi_fi_orders.payment_status', 'failed');
                    })
                    ->leftJoin('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('wi_fi_orders.created_at', '>=', $formattedStartDate)
                            ->whereDate('wi_fi_orders.created_at', '<=', $formattedEndDate);
                    })->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })->count();

                $user_orders['total_paid'] = WiFiOrders::forLocations($flattenedZoneIds)
                    ->where('status', 1)
                    ->where('payment_status', 'paid')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
                $user_orders['total_free'] = WiFiOrders::forLocations($flattenedZoneIds)
                    ->where('wi_fi_orders.status', 1)
                    ->where('wi_fi_orders.payment_status', 'free')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
                $user_orders['total_cancelled'] = WiFiOrders::forLocations($flattenedZoneIds)
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'cancelled')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
                $user_orders['total_failed'] = WiFiOrders::forLocations($flattenedZoneIds)
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'failed')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();


            } else {
                $user_orders['paid'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->whereIn('wi_fi_orders.location_id', $locations)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 1)
                    ->where('wi_fi_orders.payment_status', 'paid')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                $user_orders['free'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->whereIn('wi_fi_orders.location_id', $locations)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 1)
                    ->where('wi_fi_orders.payment_status', 'free')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                $user_orders['cancelled'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->whereIn('wi_fi_orders.location_id', $locations)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'cancelled')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                $user_orders['failed'] = DB::table('wi_fi_orders')
                    ->join('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->whereIn('wi_fi_orders.location_id', $locations)
                    ->select(DB::raw('DATE(wi_fi_orders.created_at) as date'), DB::raw('count(*) as activations'))
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'failed')
                    ->whereBetween('wi_fi_orders.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                $user_orders['total'] = WiFiOrders::forLocations($locations)
                    ->where(function ($query) {
                        $query->where('wi_fi_orders.status', 1)
                            ->orWhere('wi_fi_orders.status', 0);
                    })
                    ->where(function ($query) {
                        $query->where('wi_fi_orders.payment_status', 'paid')
                            ->orWhere('wi_fi_orders.payment_status', 'free')
                            ->orWhere('wi_fi_orders.payment_status', 'cancelled')
                            ->orWhere('wi_fi_orders.payment_status', 'failed');
                    })
                    ->leftJoin('locations', 'wi_fi_orders.location_id', '=', 'locations.id')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('wi_fi_orders.created_at', '>=', $formattedStartDate)
                            ->whereDate('wi_fi_orders.created_at', '<=', $formattedEndDate);
                    })->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })->count();

                $user_orders['total_paid'] = WiFiOrders::forLocations($locations)
                    ->where('status', 1)
                    ->where('payment_status', 'paid')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
                $user_orders['total_free'] = WiFiOrders::forLocations($locations)
                    ->where('wi_fi_orders.status', 1)
                    ->where('wi_fi_orders.payment_status', 'free')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
                $user_orders['total_cancelled'] = WiFiOrders::forLocations($locations)
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'cancelled')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
                $user_orders['total_failed'] = WiFiOrders::forLocations($locations)
                    ->where('wi_fi_orders.status', 0)
                    ->where('wi_fi_orders.payment_status', 'failed')
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('created_at', '>=', $formattedStartDate)
                            ->whereDate('created_at', '<=', $formattedEndDate);
                    })
                    ->count();
            }


        }

        $user_orders_xmin = strtotime($formattedStartDate) * 1000;
        $user_orders_xmax = strtotime($formattedEndDate) * 1000;
        $grouped = $user_orders['paid']->groupBy('date');
        $groupedfree = $user_orders['free']->groupBy('date');
        $groupedCanelled = $user_orders['cancelled']->groupBy('date');
        $groupedFailed = $user_orders['failed']->groupBy('date');

        $user_orders['paid'] = [];
        $user_orders['free'] = [];
        $user_orders['cancelled'] = [];
        $user_orders['failed'] = [];

        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');
            $temp = [$key, 0];
            if ($grouped[$key] ?? false) {
                $temp[1] = $grouped[$key][0]->activations ?? 0;
            }
            $user_orders['paid'][] = $temp;
        }

        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');

            $temp = [$key, 0];
            if ($groupedfree[$key] ?? false) {
                $temp[1] = $groupedfree[$key][0]->activations ?? 0;
            }
            $user_orders['free'][] = $temp;
        }

        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');

            $temp = [$key, 0];
            if ($groupedCanelled[$key] ?? false) {
                $temp[1] = $groupedCanelled[$key][0]->activations ?? 0;
            }
            $user_orders['cancelled'][] = $temp;
        }

        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');

            $temp = [$key, 0];
            if ($groupedFailed[$key] ?? false) {
                $temp[1] = $groupedFailed[$key][0]->activations ?? 0;
            }
            $user_orders['failed'][] = $temp;
        }

        /*else {
      $activations['breakup'] = WiFiOrders::selectRaw("DATE_FORMAT(created_at, '%m-%Y') as formatted_date, count(*) as activations")
           ->groupBy('formatted_date')
           ->forLocations($locations)
           ->where('status', 1)
           ->where($yearQuery($currentDate))
           ->get()->groupBy('formatted_date');

       $activations['total'] = WiFiOrders::forLocations($locations)->where($yearQuery($currentDate))->where('status', 1)->count();
       }*/

        $recent_orders = ZohoOrder::query()->latest()->limit(10)->get();

        $user_activations = [];
        if ($user->isAdmin()) {
            $user_activations['user_activations_verified'] = DB::table('users')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as user_orders_activations'))
                ->where('role', 2)
                ->whereNotNull('otp_verified_on')
                ->whereBetween('created_at', [$formattedStartDate, $formattedEndDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $user_activations['user_activations_pending'] = DB::table('users')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as user_orders_activations'))
                ->where('role', 2)
                ->WhereNull('otp_verified_on')
                ->whereBetween('created_at', [$formattedStartDate, $formattedEndDate])
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            $user_activations['total'] = User::where('role', 2)
                ->where(function ($query) {
                    $query->whereNotNull('otp_verified_on')
                        ->orWhereNull('otp_verified_on');
                })
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
            $user_activations['totalUserVerified'] = User::where('role', 2)
                ->where(function ($query) {
                    $query->whereNotNull('otp_verified_on');
                })
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
            $user_activations['totalUserPending'] = User::where('role', 2)
                ->where(function ($query) {
                    $query->WhereNull('otp_verified_on');
                })
                ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                    $query->whereDate('created_at', '>=', $formattedStartDate)
                        ->whereDate('created_at', '<=', $formattedEndDate);
                })
                ->count();
        }

        if ($user->isPDO()) {
            if ($locationId || $zoneId) {
                $flattenedZoneIds = array_merge([$locationId], $zoneIds);

                $user_activations['user_activations_verified'] = DB::table('users')
                    ->join('locations', 'users.location_id', '=', 'locations.id')
                    ->orWhereIn('users.location_id', $flattenedZoneIds)
                    ->select(DB::raw('DATE(users.created_at) as date'), DB::raw('COUNT(*) as user_orders_activations'))
                    ->where('role', 2)
                    ->whereNotNull('otp_verified_on')
                    ->whereBetween('users.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                $user_activations['user_activations_pending'] = DB::table('users')
                    ->join('locations', 'users.location_id', '=', 'locations.id')
                    ->orWhereIn('users.location_id', $flattenedZoneIds)
                    ->select(DB::raw('DATE(users.created_at) as date'), DB::raw('COUNT(*) as user_orders_activations'))
                    ->where('role', 2)
                    ->whereNull('otp_verified_on')
                    ->whereBetween('users.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                $user_activations['total'] = DB::table('users')
                    ->leftJoin('locations', 'users.location_id', '=', 'locations.id')
                    ->where('role', 2)
                    ->where(function ($query) {
                        $query->whereNotNull('otp_verified_on')
                            ->orWhereNull('otp_verified_on');
                    })
                    ->whereBetween(DB::raw('date(users.created_at)'), [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) use ($locationId, $zoneIds) {
                        if ($locationId) {
                            $query->where('users.location_id', $locationId);
                        } elseif ($zoneIds) {
                            $flattenedZoneIds = is_array($zoneIds) ? array_merge([$locationId], $zoneIds) : [$locationId];
                            $query->orWhereIn('users.location_id', $flattenedZoneIds);
                        }
                    })
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->count();
                $user_activations['totalUserVerified'] = DB::table('users')
                    ->leftJoin('locations', 'users.location_id', '=', 'locations.id')
                    ->where('role', 2)
                    ->where(function ($query) {
                        $query->whereNotNull('users.otp_verified_on');
                    })
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('users.created_at', '>=', $formattedStartDate)
                            ->whereDate('users.created_at', '<=', $formattedEndDate);
                    })
                    ->where(function ($query) use ($locationId, $zoneIds) {
                        if ($locationId) {
                            $query->where('users.location_id', $locationId);
                        } elseif ($zoneIds) {
                            $flattenedZoneIds = is_array($zoneIds) ? array_merge([$locationId], $zoneIds) : [$locationId];
                            $query->orWhereIn('users.location_id', $flattenedZoneIds);
                        }
                    })
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->count();
                $user_activations['totalUserPending'] = DB::table('users')
                    ->leftJoin('locations', 'users.location_id', '=', 'locations.id')
                    ->where('role', 2)
                    ->where(function ($query) {
                        $query->WhereNull('users.otp_verified_on');
                    })
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('users.created_at', '>=', $formattedStartDate)
                            ->whereDate('users.created_at', '<=', $formattedEndDate);
                    })
                    ->where(function ($query) use ($locationId, $zoneIds) {
                        if ($locationId) {
                            $query->where('users.location_id', $locationId);
                        } elseif ($zoneIds) {
                            $flattenedZoneIds = is_array($zoneIds) ? array_merge([$locationId], $zoneIds) : [$locationId];
                            $query->orWhereIn('users.location_id', $flattenedZoneIds);
                        }
                    })
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->count();

            } else {
                $user_activations['user_activations_verified'] = DB::table('users')
                    ->join('locations', 'users.location_id', '=', 'locations.id')
                    ->whereIn('users.location_id', $locations)
                    ->select(DB::raw('DATE(users.created_at) as date'), DB::raw('COUNT(*) as user_orders_activations'))
                    ->where('role', 2)
                    ->whereNotNull('otp_verified_on')
                    ->whereBetween('users.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                $user_activations['user_activations_pending'] = DB::table('users')
                    ->join('locations', 'users.location_id', '=', 'locations.id')
                    ->whereIn('users.location_id', $locations)
                    ->select(DB::raw('DATE(users.created_at) as date'), DB::raw('COUNT(*) as user_orders_activations'))
                    ->where('role', 2)
                    ->whereNull('otp_verified_on')
                    ->whereBetween('users.created_at', [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();

                $user_activations['total'] = DB::table('users')
                    ->leftJoin('locations', 'users.location_id', '=', 'locations.id')
                    ->where('role', 2)
                    ->where(function ($query) {
                        $query->whereNotNull('otp_verified_on')
                            ->orWhereNull('otp_verified_on');
                    })
                    ->whereBetween(DB::raw('date(users.created_at)'), [$formattedStartDate, $formattedEndDate])
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->count();
                $user_activations['totalUserVerified'] = DB::table('users')
                    ->leftJoin('locations', 'users.location_id', '=', 'locations.id')
                    ->where('role', 2)
                    ->where(function ($query) {
                        $query->whereNotNull('users.otp_verified_on');
                    })
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('users.created_at', '>=', $formattedStartDate)
                            ->whereDate('users.created_at', '<=', $formattedEndDate);
                    })
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->count();
                $user_activations['totalUserPending'] = DB::table('users')
                    ->leftJoin('locations', 'users.location_id', '=', 'locations.id')
                    ->where('role', 2)
                    ->where(function ($query) {
                        $query->WhereNull('users.otp_verified_on');
                    })
                    ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                        $query->whereDate('users.created_at', '>=', $formattedStartDate)
                            ->whereDate('users.created_at', '<=', $formattedEndDate);
                    })
                    ->where(function ($query) {
                        $query->where('locations.owner_id', Auth::user()->id);
                    })
                    ->count();
            }
        }

        $user_activations_xmin = strtotime($formattedStartDate) * 1000;
        $user_activations_xmax = strtotime($formattedEndDate) * 1000;
        $groupedUserVerified = $user_activations['user_activations_verified']->groupBy('date');
        $groupedUserPending = $user_activations['user_activations_pending']->groupBy('date');
        $user_activations['user_activations_verified'] = [];
        $user_activations['user_activations_pending'] = [];

        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');
            $temp = [$key, 0];
            if ($groupedUserVerified[$key] ?? false) {
                $temp[1] = $groupedUserVerified[$key][0]->user_orders_activations ?? 0;
            }
            $user_activations['user_activations_verified'] [] = $temp;
        }
        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');
            $temp = [$key, 0];
            if ($groupedUserPending[$key] ?? false) {
                $temp[1] = $groupedUserPending[$key][0]->user_orders_activations ?? 0;
            }
            $user_activations['user_activations_pending'] [] = $temp;
        }

        return compact('routers', 'payments', 'sessions', 'sessions_xmin', 'sessions_xmax', 'user_orders', 'user_orders_xmin', 'user_orders_xmax', 'recent_orders', 'user_activations', 'user_activations_xmin', 'user_activations_xmax','pdoTotalAmount');
    }

    public function locationAndZone(Request $request)
    {
        $pdo_location = [];
        $pdo_zone = [];

        if (Auth::user()->isPDO()) {
            $total = Router::with('location')->where('pdo_id', Auth::user()->id)->orWhereHas('location', function ($query) {
                $query->where('owner_id', Auth::user()->id);
            })->count();

            $locationsnew = Location::where('owner_id', Auth::user()->id)->get();

            if ($locationsnew->isNotEmpty()) {
                foreach ($locationsnew as $locationpdo) {
                    $location_id = $locationpdo->id;
                    $location_name = $locationpdo->name;

                    $pdo_location [] = array(
                        'id' => $location_id,
                        'name' => $location_name,
                    );
                }
            } else {
                $pdo_location = [];
            }

            $zones = BrandingProfile::where('pdo_id', Auth::user()->id)->get();

            if ($zones->isNotEmpty()) {
                foreach ($zones as $zone) {
                    $zone_id = $zone->id;
                    $zone_name = $zone->name;

                    $pdo_zone [] = array(
                        'id' => $zone_id,
                        'name' => $zone_name,
                    );
                }
            } else {
                $pdo_zone = [];
            }
        }
        return compact('pdo_location', 'pdo_zone');

    }

    public function loadSmsQuota(Request $request)
    {
        if (Auth::user()->isPDO()) {
            $user = Auth::user();
            $pdoSmsQuota = PdoSmsQuota::where('pdo_id', $user->id)->orderBy('created_at', 'desc')->first();
            $pdoPlan = PdoaPlan::where('id', $user->pdo_type)->first();
            if ($pdoSmsQuota != null) {
                $smsQuota = $pdoSmsQuota->sms_quota;
                $smsUsed = $pdoSmsQuota->sms_used;
                $percentageUsed = ($smsUsed / $smsQuota) * 100;
                if ($percentageUsed >= 80) {
                    $user = User::where('id',$pdoSmsQuota->pdo_id)->first();
                    $todayNotification = DB::table('notifications')
                        ->where('notifiable_id', $pdoSmsQuota->pdo_id)
                        ->where('type', 'App\Mail\SendNotificationPdoUsedSmsQuota')
                        ->whereDate('created_at', today())
                        ->exists();
                    if (!$todayNotification) {

                        event(new PdoUsedSmsQuotaEvents($user, $percentageUsed, $pdoSmsQuota));
                    } else {
                    }
                    return response()->json([
                        'pdo_plan_sms_quota' =>$pdoPlan->sms_quota,
                        'sms_used' => $pdoSmsQuota->sms_used,
                        'sms_quota' =>$pdoSmsQuota->sms_quota,
                        'renewal_date' => date('Y-m-d', strtotime($pdoSmsQuota->end_date . ' +1 day')),
                        'pdo_id' => $pdoSmsQuota->pdo_id,
                        'percentage_used' => (int)$percentageUsed,
                        'message' => 'Your SMS usage has exceeded 80% of the quota.'
                    ]);
                }
                return response()->json([
                    'renewal_date' => date('Y-m-d', strtotime($pdoSmsQuota->end_date . ' +1 day')),
                    'pdo_plan_sms_quota' =>$pdoPlan->sms_quota,
                    'pdo_id' => $pdoSmsQuota->pdo_id,
                    'sms_used' => $pdoSmsQuota->sms_used,
                    'sms_quota' =>$pdoSmsQuota->sms_quota,
                    'message' => 'Your SMS usage has exceeded 80% of the quota.'
                ]);

            } else {
                return response()->json(['message' => 'You are not authorized to access this feature.'], 403);
            }
        } else {
            return response()->json(['message' => 'You are not authorized to access this feature.'], 403);
        }
    }

     public function top_data_usage_pdo(Request $request)
     {
         $days = 30;

         $currentDate = Carbon::today();
         $previousDate = Carbon::today()->subDays($days);
         $startDate = $request->get('startDate');
         $endDate = $request->get('endDate');

         $locationId = $request->get('location');
         $zoneId = $request->get('zone');
         $zoneLocationIds = Location::where('profile_id', $zoneId)->get();

         $zoneIds = [];
         if ($zoneLocationIds->isNotEmpty()) {
             foreach ($zoneLocationIds as $zoneLocationId) {
                 $zoneIds[] = $zoneLocationId->id;
             }
         } else {
             $zoneIds = [];
         }
         //  dd($zoneLocationId);

         $startDateTimestamp = $startDate / 1000;
         $endDateTimestamp = $endDate / 1000;
         $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
         $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

         $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
         $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

         $user = Auth::user();
         if ($user->parent_id) {
             $parent = User::where('id', $user->parent_id)->first();
             $user = $parent;
         }
         $top_data_usage = [];
         $locations = Location::mine()->pluck('id')->toArray();
         if ($user->isPDO()) {
             if ($locationId || $zoneId) {
                 $flattenedZoneIds = array_merge([$locationId], $zoneIds);
                 $top_data_usage['top_data_download'] = DB::connection('mysql2')->table('radacct')
                     ->orWhereIn('location_id', $flattenedZoneIds)
                     ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('ROUND(SUM(acctinputoctets) / (1024*1024), 2) as top_data_download'))
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->groupBy('date', 'location_id')
                     ->get();
                 $query = DB::connection('mysql2')->table('radacct')
                     ->orWhereIn('location_id', $flattenedZoneIds)
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate]);

                 $top_data_usage['top_data_upload'] = $query
                     ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('ROUND(SUM(acctoutputoctets) / (1024*1024), 2) as top_data_upload'))
                     ->groupBy(DB::raw('DATE(acctstarttime)'))
                     ->get();

                 $top_data_usage['total_data_usage'] = round($query
                     ->sum(DB::raw('(acctoutputoctets + acctinputoctets) / (1024*1024)')), 2);

                 $total_data_download = $query->sum('acctinputoctets');
                 $top_data_usage['total_data_download'] = round($total_data_download / (1024 * 1024), 2);

                 $total_data_upload = $query->sum('acctoutputoctets');
                 $top_data_usage['total_data_upload'] = round($total_data_upload / (1024 * 1024), 2);

             } else {
                 $top_data_usage['top_data_download'] = DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $locations)
                     ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('ROUND(SUM(acctinputoctets) / (1024*1024), 2) as top_data_download'))
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->groupBy('date', 'location_id')
                     ->orderBy('date')
                     ->get();
                 //return  $top_data_usage;
                 $top_data_usage['top_data_upload'] = DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $locations)
                     ->select( DB::raw('DATE(acctstarttime) as date'), DB::raw('ROUND (SUM(acctoutputoctets) / (1024*1024), 2) as top_data_upload'))
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->groupBy('date', 'location_id')
                     ->orderBy('date')
                     ->get();

                 $top_data_usage['total_data_usage'] = round(DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $locations)
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->sum(DB::raw('(acctoutputoctets + acctinputoctets) / (1024*1024)')), 2);

                 $total_data_download = DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $locations)
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->sum('acctinputoctets');

                 $top_data_usage['total_data_download'] = round($total_data_download / (1024 * 1024), 2);

                 $total_data_upload = DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $locations)
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->sum('acctoutputoctets');

                 $top_data_usage['total_data_upload'] = round($total_data_upload / (1024 * 1024), 2);
             }
         }

         $top_data_usage_xmin = strtotime($formattedStartDate) * 1000;
         $top_data_usage_xmax = strtotime($formattedEndDate) * 1000;
         $groupedDataDownload = $top_data_usage['top_data_download']->groupBy('date');
         $groupedDataUpload = $top_data_usage['top_data_upload']->groupBy('date');
         $top_data_usage['top_data_download'] = [];
         $top_data_usage['top_data_upload'] = [];

         for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
             $key = $i->format('Y-m-d');
             $temp = [$key, 0];
             if ($groupedDataDownload[$key] ?? false) {
                 $temp[1] = $groupedDataDownload[$key][0]->top_data_download ?? 0;
             }
             $top_data_usage['top_data_download'] [] = $temp;
         }
         for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
             $key = $i->format('Y-m-d');
             $temp = [$key, 0];
             if ($groupedDataUpload[$key] ?? false) {
                 $temp[1] = $groupedDataUpload[$key][0]->top_data_upload ?? 0;
             }
             $top_data_usage['top_data_upload'] [] = $temp;
         }

         return compact('top_data_usage', 'top_data_usage_xmin', 'top_data_usage_xmax');

     }

     public function topUsersLocation(Request $request)
     {
         $days = 30;
         $startDate = $request->get('startDate');
         $endDate = $request->get('endDate');

         $locationId = $request->get('location');
         $zoneId = $request->get('zone');
         $zoneLocationIds = Location::where('profile_id', $zoneId)->get();

         $zoneIds = [];
         if ($zoneLocationIds->isNotEmpty()) {
             foreach ($zoneLocationIds as $zoneLocationId) {
                 $zoneIds[] = $zoneLocationId->id;
                 $zoneIds[] = $zoneLocationId->name;
             }
         } else {
             $zoneIds = [];
         }
         //  dd($zoneLocationId);

         $startDateTimestamp = $startDate / 1000;
         $endDateTimestamp = $endDate / 1000;
         $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
         $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

         $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
         $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

         $user = Auth::user();
         if ($user->parent_id) {
             $parent = User::where('id', $user->parent_id)->first();
             $user = $parent;
         }
         $locations = Location::mine()->pluck('id')->toArray();
         $top_user_location = [];
         //$location_names = [];
         if ($user->isPDO()) {
             if ($locationId || $zoneId) {
                 $flattenedZoneIds = array_merge([$locationId], $zoneIds);
                 $top_user_location['top_user'] = DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $flattenedZoneIds)
                     ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('count(distinct(username)) as top_user'))
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->groupBy(DB::raw('DATE(acctstarttime)'))
                     ->orderBy('date')
                     ->get();

                 $query =  $top_user_location['top_user'];

                 $total_user_count = 0;
                 foreach ($query as $data) {
                     $total_user_count += $data->top_user;
                 }
                 $top_user_location['total_user'] = $total_user_count;

             } else {
                 //$locationsnew = Location::select('id as location_id', 'name as location_name')->get()->toArray();
                 $top_user_location['top_user'] = DB::connection('mysql2')->table('radacct')
                     ->whereIn('location_id', $locations)
                     ->select('location_id', DB::raw('DATE(acctstarttime) as date'), DB::raw('count(distinct(username)) as top_user'))
                     ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
                     ->groupBy('location_id', DB::raw('DATE(acctstarttime)'))
                     ->orderBy(DB::raw('DATE(acctstarttime)'), 'asc')
                     ->get();

                 $query = $top_user_location['top_user'];

                 $total_user_count = 0;
                 foreach ($query as $data) {
                     $total_user_count += $data->top_user;
                 }
                 $top_user_location['total_user'] = $total_user_count;
             }
         }

         $top_user_xmin = strtotime($formattedStartDate) * 1000;
         $top_user_xmax = strtotime($formattedEndDate) * 1000;
         $groupedTopUser = $top_user_location['top_user']->groupBy('date');
         $top_user_location['top_user'] = [];

        /* foreach ($locationsnew as $location) {
             // Check if $location is an array and if it contains 'location_id' key
                 $radacctData = collect($query)->firstWhere('location_id', $location['location_id']);
                 $user_count = $radacctData ? $radacctData->top_user : 0;

                 if ($user_count > 0) {
                     $top_user_location['top_user'] = [
                         'location_name' => $location['location_name']
                     ];
                 }
         }*/

       // return $radacctData;
        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
             $key = $i->format('Y-m-d');
             $temp = [$key, 0];
             if ($groupedTopUser[$key] ?? false) {
                 $temp[1] = $groupedTopUser[$key][0]->top_user ?? 0;
             }
             $top_user_location['top_user'][] = $temp;
         }

        /* $top_user_location['top_user'][''] = $location_names;

       // Populate $top_user_location['top_user'] with data for each day
         for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
             $key = $i->format('Y-m-d');
             $temp = [$key, 0];
             if ($groupedTopUser[$key] ?? false) {
                 $temp[1] = $groupedTopUser[$key][0]->top_user ?? 0;
             }
             $top_user_location['top_user']['data'][] = $temp;
         }*/

         return compact('top_user_location', 'top_user_xmin', 'top_user_xmax');
     }
     
    //  public function topUsersLocation(Request $request)
    // {
    //     $startDate = $request->get('startDate') / 1000;
    //     $endDate   = $request->get('endDate') / 1000;

    //     $locationId = $request->get('location');
    //     $zoneId     = $request->get('zone');
    //     $carbonStartDate = Carbon::createFromTimestamp($startDate);
    //     $carbonEndDate   = Carbon::createFromTimestamp($endDate);

    //     $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');
    //     $formattedEndDate   = $carbonEndDate->format('Y-m-d H:i:s');

    //     $user = Auth::user();
    //     if ($user->parent_id) {
    //         $user = User::find($user->parent_id);
    //     }

    //     $locations = Location::mine()->pluck('id')->toArray();
    //     $result = $this->buildTopUserLocationSeries(
    //         $formattedStartDate,
    //         $formattedEndDate,
    //         $carbonStartDate,
    //         $carbonEndDate,
    //         $locationId,
    //         $zoneId,
    //         $locations
    //     );

    //     return [
    //         'top_user_location' => $result,
    //         'top_user_xmin'     => strtotime($formattedStartDate) * 1000,
    //         'top_user_xmax'     => strtotime($formattedEndDate) * 1000,
    //     ];
    // }


    // private function buildTopUserLocationSeries(
    //     $formattedStartDate,
    //     $formattedEndDate,
    //     Carbon $carbonStartDate,
    //     Carbon $carbonEndDate,
    //     $locationId,
    //     $zoneId,
    //     array $locations
    // ) {
    //     $totalUserCount = 0;

    //     /**
    //      * ------------------------------------------------
    //      * CASE 1: Location OR Zone selected (single series)
    //      * ------------------------------------------------
    //      */
    //     if ($locationId || $zoneId) {

    //         $zoneLocationIds = [];
    //         if ($zoneId) {
    //             $zoneLocationIds = Location::where('profile_id', $zoneId)
    //                 ->pluck('id')
    //                 ->toArray();
    //         }

    //         $filterIds = array_filter(array_merge([$locationId], $zoneLocationIds));

    //         $data = DB::connection('mysql2')->table('radacct')
    //             ->whereIn('location_id', $filterIds)
    //             ->select(
    //                 DB::raw('DATE(acctstarttime) as date'),
    //                 DB::raw('count(distinct(username)) as top_user')
    //             )
    //             ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
    //             ->groupBy(DB::raw('DATE(acctstarttime)'))
    //             ->orderBy('date')
    //             ->get()
    //             ->keyBy('date');

    //         $seriesData = [];
    //         for ($i = $carbonStartDate->copy(); $i->lte($carbonEndDate); $i->addDay()) {
    //             $date = $i->format('Y-m-d');
    //             $value = $data[$date]->top_user ?? 0;
    //             $seriesData[] = [$date, $value];
    //             $totalUserCount += $value;
    //         }

    //         return [
    //             'top_user'   => [[
    //                 'name' => 'Total Users',
    //                 'data' => $seriesData
    //             ]],
    //             'total_user' => $totalUserCount
    //         ];
    //     }

    //     /**
    //      * ------------------------------------------------
    //      * CASE 2: DEFAULT → ALL locations (multi-series)
    //      * ------------------------------------------------
    //      */

    //     /** Fetch RADACCT data (radius DB only) */

    //     $rawData = DB::connection('mysql2')->table('radacct')
    //         ->whereIn('location_id', $locations)
    //         ->select(
    //             'location_id',
    //             DB::raw('DATE(acctstarttime) as date'),
    //             DB::raw('count(distinct(username)) as top_user')
    //         )
    //         ->whereBetween('acctstarttime', [$formattedStartDate, $formattedEndDate])
    //         ->groupBy('location_id', DB::raw('DATE(acctstarttime)'))
    //         // ->orderBy('date')
    //         ->orderBy(DB::raw('DATE(acctstarttime)'), 'asc')
    //         ->get();

    //     /** Fetch location names from APP DB */
    //     $locationNames = Location::whereIn('id', $locations)
    //         ->pluck('name', 'id')
    //         ->toArray();

    //     $series = [];
    //     $groupedByLocation = $rawData->groupBy('location_id');

    //     foreach ($groupedByLocation as $locationId => $records) {

    //         $locationName = $locationNames[$locationId] ?? 'Location ' . $locationId;
    //         $records = $records->keyBy('date');

    //         $data = [];
    //         for ($i = $carbonStartDate->copy(); $i->lte($carbonEndDate); $i->addDay()) {
    //             $date = $i->format('Y-m-d');
    //             $value = $records[$date]->top_user ?? 0;
    //             $data[] = [$date, $value];
    //             $totalUserCount += $value;
    //         }

    //         $series[] = [
    //             'name' => $locationName,
    //             'data' => $data
    //         ];
    //     }

    //     return [
    //         'top_user'   => $series,
    //         'total_user' => $totalUserCount
    //     ];
    // }


    public function userSessions(Request $request)
    {
        $startDate = $request->get('startDate');
        $endDate = $request->get('endDate');
        $startDateTimestamp = $startDate / 1000;
        $endDateTimestamp = $endDate / 1000;
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length");

        $carbonStartDate = Carbon::createFromTimestamp($startDateTimestamp);
        $formattedStartDate = $carbonStartDate->format('Y-m-d H:i:s');

        $carbonEndDate = Carbon::createFromTimestamp($endDateTimestamp);
        $formattedEndDate = $carbonEndDate->format('Y-m-d H:i:s');

        $locations = Location::mine()->pluck('id')->toArray();

        $sessions['current_month_breakup'] = DB::connection('mysql2')
            ->table('radacct')
            ->whereIn('location_id', $locations)
            ->select(DB::raw('DATE(acctstarttime) as date'), DB::raw('count(*) as sessions'))
            ->groupBy('date')
            ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                $query->whereDate('acctstarttime', '>=', $formattedStartDate)
                    ->whereDate('acctstarttime', '<=', $formattedEndDate);
            })
            ->get();

        $sessions['current_month'] = DB::connection('mysql2')
            ->table('radacct')
            ->whereIn('location_id', $locations)
            ->where(function ($query) use ($formattedStartDate, $formattedEndDate) {
                $query->whereDate('acctstarttime', '>=', $formattedStartDate)
                    ->whereDate('acctstarttime', '<=', $formattedEndDate);
            })
            ->count();

        $sessions_xmin = strtotime($formattedStartDate) * 1000;
        $sessions_xmax = strtotime($formattedEndDate) * 1000;

        $grouped = $sessions['current_month_breakup']->groupBy('date');
        $data = [];
        for ($i = $carbonStartDate->clone(); $i->lte($carbonEndDate); $i->addDay()) {
            $key = $i->format('Y-m-d');
            $temp = [$key, 0];
            if ($grouped[$key] ?? false) {
                $temp[1] = $grouped[$key][0]->sessions ?? 0;
            }
            $data[] = $temp;
        }
        $sessions['current_month_breakup'] = $data;

        // $payoutData = $this->getPayout();

        return compact('sessions', 'sessions_xmin', 'sessions_xmax');

    }

     public function top_data_usage($period)
     {
         switch ($period) {
             case 'month':
                 $interval = '-1 month';
                 break;
             case 'month3':
                 $interval = '-3 months';
                 break;
             case 'month6':
                 $interval = '-6 months';
                 break;
             case 'year':
                 $interval = '-1 year';
                 break;
             default:
                 $interval = '-1 month';
                 break;
         }

         $now = date_create();
         date_add($now, date_interval_create_from_date_string($interval));
         $date = $now->format('Y-m-d');

         $locations = Location::select('id as location_id', 'name as location_name')->get()->toArray();

         $radacct = DB::connection('mysql2')->table('radacct')
             ->select('location_id', DB::raw('SUM(acctinputoctets + acctoutputoctets) as total_data_usage'))
             ->where('acctstarttime', '>', $date)
             ->groupBy('location_id')
             ->orderByDesc('total_data_usage')
             ->get()->toArray();

         $output = [];
         foreach ($locations as $location) {
             $radacctData = collect($radacct)->firstWhere('location_id', $location['location_id']);
             $totalDataUsage = $radacctData ? $radacctData->total_data_usage : 0;

             if ($totalDataUsage > 0) {
                 $output[] = [
                     'location_id' => $location['location_id'],
                     'location_name' => $location['location_name'],
                     'total_data_usage' => $totalDataUsage,
                 ];
             }
         }

         return response()->json([
             'success' => true,
             'data' => $output,
         ]);
     }

     

    public function top_data_usage_old($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');

        $db1Name = config('database.connections.mysql.database');
        $db2Name = config('database.connections.mysql2.database');

        $query =  DB::table($db1Name.'.locations')->leftjoin($db2Name.'.radacct as radacct', $db1Name.'.locations.id', '=', $db2Name.'.radacct.location_id');

        $output = $query->select($db1Name.'.locations.id as location_id', $db1Name.'.locations.name as location_name', DB::raw('SUM('.$db2Name.'.radacct.acctinputoctets + '.$db2Name.'.radacct.acctoutputoctets) as total_data_usage'))
            ->where($db2Name.'.radacct.acctstarttime', '>', $date)
            ->groupBy($db1Name.'.locations.id', $db1Name.'.locations.name')
            ->orderBy('total_data_usage', 'desc')
            ->get()->toArray();

        $output = array_map(function($object){
            return (array) $object;
        }, $output);


        for ($i=0; $i < count($output); $i++) {
            if(is_null($output[$i]['total_data_usage'])) $output[$i]['total_data_usage'] = 0;
        }
        return response()->json([
            'success' => true,
            'data' => $output
        ]);
    }

    public function top_users_location($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');

        $locations = Location::select('id as location_id', 'name as location_name')->get()->toArray();

        $radacct = DB::connection('mysql2')->table('radacct')
            ->select('location_id', DB::raw('count(distinct(username)) as user_count'))
            ->where('acctstarttime', '>', $date)
            ->groupBy('location_id')
            ->orderByDesc('user_count')
            ->get()->toArray();

        $output = [];
        foreach ($locations as $location) {
            $radacctData = collect($radacct)->firstWhere('location_id', $location['location_id']);
            $user_count = $radacctData ? $radacctData->user_count : 0;

            if ($user_count > 0) {
                $output[] = [
                    'location_id' => $location['location_id'],
                    'location_name' => $location['location_name'],
                    'user_count' => $user_count,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $output
        ]);
    }

    public function top_users_location_old($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');
        /*
        $result = Location::leftJoin('users', 'locations.owner_id', '=', 'users.id')
                    ->select('locations.id as location_id', 'locations.name as location_name', DB::raw('count(users.id) as user_count'))
                    ->where('users.created_at', '>', $date)
                    ->groupBy('locations.id', 'locations.name')
                    ->orderBy('user_count', 'desc')
                    ->get();*/
        if(Schema::hasTable('radius.radacct')) {
            $result = DB::table('radius.radacct')->leftJoin('wifiadmin.locations', 'wifiadmin.locations.id', '=', 'radius.radacct.location_id')
                ->select('locations.id as location_id', 'locations.name as location_name', DB::raw('count(distinct(username)) as user_count'))
                ->where('radius.radacct.acctstarttime', '>', $date)
                ->groupBy('radius.radacct.location_id')
                ->orderBy('user_count', 'desc')
                ->get()->toArray();
        } else {
            $result = [];
        }

        $result = array_map(function($object){
            return (array) $object;
        }, $result);

        for ($i=0; $i < count($result); $i++) {
            if(is_null($result[$i]['user_count'])) $result[$i]['user_count'] = 0;
        }
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function top_selling_location($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');
        $result = Location::leftJoin('wi_fi_orders', 'locations.id', '=', 'wi_fi_orders.location_id')
            ->select('locations.id as location_id', 'locations.name as location_name', DB::raw('count(wi_fi_orders.id) as order_count'))
            ->where('wi_fi_orders.created_at', '>', $date)
            ->where('wi_fi_orders.status', '1')
            ->groupBy('locations.id', 'locations.name')
            ->orderBy('order_count', 'desc')
            ->get();
        for ($i=0; $i < count($result); $i++) {
            if(is_null($result[$i]['order_count'])) $result[$i]['user_count'] = 0;
        }
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function top_daily_online($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                $count = 30 * 24 * 60;
                break;
            case 'month3':
                $days = '- 3 months';
                $count = 3 * 30 * 24 * 60;
                break;
            case 'month6':
                $days = '- 6 month';
                $count = 6 * 30 * 24 * 60;
                break;
            case 'year':
                $days = '- 1 year';
                $count = 12 * 30 * 24 * 60;
                break;

            default:
                $days = '- 1 month';
                $count = 30 * 24 * 60;
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');
        $result = Location::leftJoin('routers', 'locations.id', '=', 'routers.location_id')
            ->leftJoin('wi_fi_statuses', 'routers.id', '=', 'wi_fi_statuses.wifi_router_id')
            ->select('locations.id as location_id', 'locations.name as location_name', DB::raw('count(wi_fi_statuses.id) as online_percent'))
            ->where('wi_fi_statuses.created_at', '>', $date)
            ->groupBy('locations.id', 'locations.name')
            ->orderBy('online_percent', 'desc')
            ->get();
        for ($i=0; $i < count($result); $i++) {
            if(is_null($result[$i]['online_percent'])) $result[$i]['online_percent'] = 0;
            $result[$i]['online_percent'] = $result[$i]['online_percent']*100 / $count;
        }
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function top_data_client($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');

        $users = User::select('id as user_id', 'phone as phone', 'first_name', 'last_name', 'email')->get()->toArray();

        $radacct = DB::connection('mysql2')->table('radacct')
            ->select('username', DB::raw('SUM(acctinputoctets + acctoutputoctets) as data_usage'))
            ->where('acctstarttime', '>', $date)
            ->groupBy('username')
            ->orderByDesc('data_usage')
            ->get()->toArray();

        $output = [];
        foreach ($users as $user) {
            $radacctData = collect($radacct)->firstWhere('username', $user['phone']);
            $data_usage = $radacctData ? $radacctData->data_usage : 0;

            if ($data_usage > 0) {
                $output[] = [
                    'phone' => $user['phone'],
                    'email' => $user['email'],
                    'data_usage' => $data_usage,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $output
        ]);
    }

    public function top_data_client_old($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');
        $db1Name = config('database.connections.mysql.database');
        $db2Name = config('database.connections.mysql2.database');

        /*$result = User::leftJoin('locations', 'users.id', '=', 'locations.owner_id')->leftjoin('immunity_forge.radacct as radacct', 'locations.id', '=', 'radacct.location_id')
                    ->select('users.id as user_id', 'users.first_name', 'users.last_name', 'users.email', DB::raw('SUM(radacct.acctinputoctets + radacct.acctoutputoctets) as data_usage'))
                    ->where('radacct.acctstarttime', '>', $date)
                    ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
                    ->orderBy('data_usage', 'desc')
                    ->get();

        $result = DB::table($db1Name.'.users')->leftJoin($db1Name.'.locations', $db1Name.'.users.id', '=', $db1Name.'.locations.owner_id')->leftjoin($db2Name.'.radacct as radacct', $db1Name.'.locations.id', '=', $db2Name.'.radacct.location_id')
                    ->select($db1Name.'.users.id as user_id', $db1Name.'.users.first_name', $db1Name.'.users.last_name', $db1Name.'.users.email', DB::raw('SUM('.$db2Name.'.radacct.acctinputoctets + '.$db2Name.'.radacct.acctoutputoctets) as data_usage'))
                    ->where($db2Name.'.radacct.acctstarttime', '>', $date)
                    ->groupBy($db1Name.'.users.id', $db1Name.'.users.first_name', $db1Name.'.users.last_name', $db1Name.'.users.email')
                    ->orderBy('data_usage', 'desc')
                    ->get()->toArray();*/
        if (Schema::hasTable($db1Name.'.users')) {
            $result = DB::table($db1Name.'.users')->leftjoin($db2Name.'.radacct as radacct', $db1Name.'.users.phone', '=', $db2Name.'.radacct.username')
                ->select($db1Name.'.users.id as user_id',$db1Name.'.users.phone as phone', $db1Name.'.users.first_name', $db1Name.'.users.last_name', $db1Name.'.users.email', DB::raw('SUM('.$db2Name.'.radacct.acctinputoctets + '.$db2Name.'.radacct.acctoutputoctets) as data_usage'))
                ->where($db2Name.'.radacct.acctstarttime', '>', $date)
                ->groupBy($db1Name.'.users.id')
                ->orderBy('data_usage', 'desc')
                ->get()->toArray();

        } else {
            $result = []    ;
        }

        $result = array_map(function($object){
            return (array) $object;
        }, $result);

        for ($i=0; $i < count($result); $i++) {
            if(is_null($result[$i]['data_usage'])) $result[$i]['data_usage'] = 0;
        }
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function top_paying_client($period)
    {
        switch ($period) {
            case 'month':
                $days = '- 1 month';
                break;
            case 'month3':
                $days = '- 3 months';
                break;
            case 'month6':
                $days = '- 6 month';
                break;
            case 'year':
                $days = '- 1 year';
                break;

            default:
                $days = '- 1 month';
                break;
        }
        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');

        $result = User::leftJoin('wi_fi_orders', 'users.id', '=', 'wi_fi_orders.owner_id')
            ->select('users.id as user_id', 'users.first_name', 'users.last_name', 'users.email', DB::raw('count(wi_fi_orders.id) as order_count'))
            ->where('wi_fi_orders.created_at', '>', $date)
            ->where('wi_fi_orders.status', '1')
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email')
            ->orderBy('order_count', 'desc')
            ->get();
        for ($i=0; $i < count($result); $i++) {
            if(is_null($result[$i]['order_count'])) $result[$i]['order_count'] = 0;
        }
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function total_user_wifi_used_session()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $logged_in_user_id = $user->id;

        $total_user_wifi_used_session = DB::select("SELECT
        DATE(session_start_time) AS date,
        SUM(uploads) AS total_uploads,
        SUM(downloads) AS total_downloads
        FROM
            user_session_logs
        JOIN
            locations ON locations.id = user_session_logs.location_id
        JOIN
            users ON users.id = locations.owner_id
        WHERE
            users.is_parentId = $logged_in_user_id
        AND
        session_start_time >= DATE_FORMAT(NOW() - INTERVAL DAY(NOW())-1 DAY, '%Y-%m-%d 00:00:00')
        AND session_start_time <= NOW()
        GROUP BY
            DATE(session_start_time)");

        return response()->json([
            'success' => true,
            'data' => $total_user_wifi_used_session
        ]);
    }

    public function total_created_session()
    {

        $total_created_session = DB::select("SELECT
            DATE(created_at) AS date,
            COUNT(DISTINCT session_id) AS session_count
            FROM
                user_session_logs
            WHERE
                DATE(created_at) >= DATE_FORMAT(NOW() - INTERVAL DAY(NOW())-1 DAY, '%Y-%m-01')
                AND DATE(created_at) <= now()
            GROUP BY
                DATE(created_at)
            ORDER BY
                DATE(created_at) ASC");

        return response()->json([
            'success' => true,
            'data' => $total_created_session
        ]);
    }

    public function live_pdo_uptime()
    {

        $days = '- 1 month';
        $count = 30 * 24 * 60;

        $now = date_create(date("Y-m-d"));
        date_add($now, date_interval_create_from_date_string($days));
        $date = $now->format('Y-m-d');

        $live_pdo_uptime = Location::leftJoin('routers', 'locations.id', '=', 'routers.location_id')
            ->leftJoin('wi_fi_statuses', 'routers.id', '=', 'wi_fi_statuses.wifi_router_id')
            ->select('locations.id as location_id', 'locations.name as location_name', DB::raw('count(wi_fi_statuses.id) as online_percent'))
            ->where('wi_fi_statuses.created_at', '>', $date)
            ->groupBy('locations.id', 'locations.name')
            ->orderBy('online_percent', 'desc')
            ->get();

        for ($i=0; $i < count($live_pdo_uptime); $i++) {
            if(is_null($live_pdo_uptime[$i]['online_percent'])) $live_pdo_uptime[$i]['online_percent'] = 0;
            $live_pdo_uptime[$i]['online_percent'] = $live_pdo_uptime[$i]['online_percent']*100 / $count;
        }

        return response()->json([
            'success' => true,
            'data' => $live_pdo_uptime
        ]);
    }
        public function wifiStatusGraph(Request $request)
        {
            $start = Carbon::createFromTimestampMs($request->get('startDate'));
            $end = Carbon::createFromTimestampMs($request->get('endDate'));

            $location = $request->get('loc', '');
            $accesspoint = $request->get('ap', '');
            $threshold = $request->get('threshold', 50);

            // interval = hourly, daily, etc.
            $interval = $request->get('interval', 'daily');

            // NEW → dynamic minutes for graph grouping (default: 10 minutes)
            $minutesInterval = intval($request->get('minutes', 10));  
            if ($minutesInterval < 1) $minutesInterval = 10;

            $user = Auth::user();
            if ($user->parent_id) {
                $user = User::find($user->parent_id);
            }

            $userId = $user->id;

            // Fetch routers
            $allRoutersQuery = DB::table('routers')
                ->where('pdo_id', $userId)
                ->select('id', 'created_at');

            if (!empty($location)) {
                $allRoutersQuery->where('location_id', $location);
            }

            if (!empty($accesspoint)) {
                $allRoutersQuery->where('id', $accesspoint);
            }

            $allRouters = $allRoutersQuery->get();

            // Time grouping for SQL
            if ($interval === 'hourly') {
                // Group by hour only inside DB
                $timeFormat = '%Y-%m-%d %H:00:00';
                $alias = 'log_hour';
            } else {
                // Daily grouping
                $timeFormat = '%Y-%m-%d';
                $alias = 'log_date';
            }

            // Query WiFi logs
            $wifiLogs = DB::table('wi_fi_statuses')
                ->select(
                    'wifi_router_id',
                    DB::raw("DATE_FORMAT(created_at, '$timeFormat') as $alias"),
                    DB::raw('COUNT(*) as records')
                )
                ->whereIn('wifi_router_id', $allRouters->pluck('id'))
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('wifi_router_id', $alias)
                ->get();

            $logsGrouped = $wifiLogs->groupBy($alias);

            // Build TIMELINE based on selected interval
            if ($interval === 'hourly') {
                // 10 min (or dynamic) steps inside each hour
                $step = $minutesInterval . ' minutes';
            } else {
                // default: 1 day
                $step = '1 day';
            }

            $period = CarbonPeriod::create($start, $step, $end);

            $data = [];

            foreach ($period as $time) {

                // UI label
                if ($interval === 'hourly') {
                    $label = $time->format('d-m-Y H:i');
                    $timeKey = $time->format('Y-m-d H:00:00'); // actual logs grouped by hour
                } else {
                    $label = $time->format('d-m-Y');
                    $timeKey = $time->format('Y-m-d');
                }

                $recordsForTime = $logsGrouped->get($timeKey, collect());

                // Routers which exist at this point
                $routersForTime = $allRouters->filter(fn($r) => Carbon::parse($r->created_at)->lte($time));
                $totalRouters = $routersForTime->count();
                $onlineRouters = 0;

                foreach ($routersForTime as $router) {
                    $routerLog = $recordsForTime->firstWhere('wifi_router_id', $router->id);

                    $records = $routerLog->records ?? 0;

                    // expected records based on interval
                    if ($interval === 'hourly') {
                        $expected = 60 / $minutesInterval;  // Example: 10 min = 6 logs per hour
                    } else {
                        $expected = 24 * (60 / $minutesInterval); // daily based on minutes
                    }

                    $percentageOnline = ($records / $expected) * 100;

                    if ($percentageOnline >= $threshold) {
                        $onlineRouters++;
                    }
                }

                $offlineRouters = $totalRouters - $onlineRouters;

                $onlinePercent = $totalRouters > 0
                    ? round(($onlineRouters / $totalRouters) * 100, 2)
                    : 0;

                $offlinePercent = 100 - $onlinePercent;

                $data[] = [
                    'label' => $label,
                    'ap_online' => $onlineRouters,
                    'ap_offline' => $offlineRouters,
                    'total_ap' => $totalRouters,
                    'online_percent' => $onlinePercent,
                    'offline_percent' => $offlinePercent,
                ];
            }

            return response()->json($data);
        }

        public function exportWifiStatus(Request $request)
        {
            $start = Carbon::createFromTimestampMs($request->get('startDate'));
            $end = Carbon::createFromTimestampMs($request->get('endDate'));
            $threshold = $request->get('threshold', 50);
            $interval = $request->get('interval', 'daily');
            

            // Get chart data using same logic
            $graphResponse = $this->wifiStatusGraph($request);
            $data = $graphResponse->getData(true);

            // Adjust filename by interval
            $fileName = 'WiFi_Status_' . ucfirst($interval) . '_' .
                $start->format('d-m-Y_Hi') . '_to_' . $end->format('d-m-Y_Hi') . '.xlsx';

            // Try using Maatwebsite Excel if available; fall back to CSV if not.
            try {
                if (class_exists('\Maatwebsite\Excel\Facades\Excel')) {
                    return \Maatwebsite\Excel\Facades\Excel::download(
                        new WifiStatusExport($data, $start, $end, $interval),
                        $fileName
                    );
                }
            } catch (\Throwable $e) {
                // Log and fall back to CSV below
                Log::error('Excel export failed: ' . $e->getMessage());
            }

            // Fallback: generate CSV manually so export still works without the Excel package
            $csvLines = [];
            // header
            $csvLines[] = [$interval === 'hourly' ? 'Hour' : 'Date', 'Total AP', 'AP Online', 'AP Offline', 'Online %', 'Offline %'];
            foreach ($data as $row) {
                $csvLines[] = [$row['label'], $row['total_ap'], $row['ap_online'], $row['ap_offline'], $row['online_percent'], $row['offline_percent']];
            }

            $callback = function () use ($csvLines) {
                $fh = fopen('php://output', 'w');
                foreach ($csvLines as $line) {
                    fputcsv($fh, $line);
                }
                fclose($fh);
            };

            $csvFileName = str_replace('.xlsx', '.csv', $fileName);
            return response()->streamDownload($callback, $csvFileName, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $csvFileName . '"',
            ]);
        }

        public function apUptimeTable(Request $request)
        {
            $start = Carbon::createFromTimestampMs($request->get('startDate'));
            $end = Carbon::createFromTimestampMs($request->get('endDate'));
            $interval = $request->get('interval', 'daily'); // optional
            $user = Auth::user();

            if ($user->parent_id) {
                $user = User::find($user->parent_id);
            }

            $userId = $user->id;

            // Get all routers for this PDO
            $routers = DB::table('routers')
                ->leftJoin('locations', 'routers.location_id', '=', 'locations.id')
                ->where('pdo_id', $userId)
                ->select('routers.id', 'routers.name as name', 'routers.created_at as created_at', 'locations.name as location_name')
                ->get();

                // Get status logs grouped by router
            $wifiLogs = DB::table('wi_fi_statuses')
                ->select(
                    'wifi_router_id',
                    DB::raw('COUNT(*) as records')
                )
                ->whereBetween('created_at', [$start, $end])
                ->whereIn('wifi_router_id', $routers->pluck('id'))
                ->groupBy('wifi_router_id')
                ->get()
                ->keyBy('wifi_router_id');

            $results = [];

            // Total minutes in date range
            $totalMinutes = $start->diffInMinutes($end);

            foreach ($routers as $router) {

                $location = $router->location_name;
                $records = $wifiLogs[$router->id]->records ?? 0;

                if($records > $totalMinutes){ $records = $totalMinutes; }

                // Percentage uptime
                $percentageOnline = $totalMinutes > 0
                    ? round(($records / $totalMinutes) * 100, 2)
                    : 0;

                $results[] = [
                    'router_id' => $router->id,
                    'router_name' => $router->name ?? 'Router ' . $router->id,
                    'location_name' => $location,
                    'percentage_online' => $percentageOnline,
                    'records' => $records,
                ];
            }

            return response()->json($results);
        }

    public function locationAP(Request $request){

        $location = $request->get('loc', '');
        // dd($location);
        $result = [];
        if(!empty($location)){
            $routers = Router::where("location_id", $location)->select('id', 'mac_address');
            // dd($routers->toSql());
            $result = $routers->get();
        }
        return response()->json($result);

    }

    public function locationUptimeTable(Request $request)
    {
        $start = Carbon::createFromTimestampMs($request->get('startDate'));
        $end   = Carbon::createFromTimestampMs($request->get('endDate'));

        $user = Auth::user();
        if ($user->parent_id) {
            $user = User::find($user->parent_id);
        }

        $userId = $user->id;

        // Router list including location
        $routers = DB::table('routers')
            ->leftJoin('locations', 'routers.location_id', '=', 'locations.id')
            ->where('routers.pdo_id', $userId)
            ->select(
                'routers.id',
                'routers.name as router_name',
                'locations.id as location_id',
                DB::raw('COALESCE(locations.name, "Unassigned") as location_name')
            )
            ->get();

        $routerIds = $routers->pluck('id');

        // Status logs count per router
        $wifiLogs = DB::table('wi_fi_statuses')
            ->select(
                'wifi_router_id',
                DB::raw('COUNT(*) as records')
            )
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('wifi_router_id', $routerIds)
            ->groupBy('wifi_router_id')
            ->get()
            ->keyBy('wifi_router_id');

        // Total minutes in date range
        $totalMinutes = max($start->diffInMinutes($end), 1);

        // --- Build map by location ---
        $locationStats = [];

        foreach ($routers as $router) {

            $location = $router->location_name;
            $records  = $wifiLogs[$router->id]->records ?? 0;

            if ($records > $totalMinutes) {
                $records = $totalMinutes;
            }

            if (!isset($locationStats[$location])) {
                $locationStats[$location] = [
                    'location_name' => $location,
                    'routers' => 0,
                    'total_records' => 0
                ];
            }

            $locationStats[$location]['routers']++;
            $locationStats[$location]['total_records'] += $records;
        }

        // Final percentage calculation
        $results = [];

        foreach ($locationStats as $row) {
            $maxRecords = $row['routers'] * $totalMinutes;

            $percentage = $maxRecords > 0
                ? round(($row['total_records'] / $maxRecords) * 100, 2)
                : 0;

            $results[] = [
                'location_name' => $row['location_name'],
                'router_count'  => $row['routers'],
                'percentage_online' => $percentage
            ];
        }

        return response()->json($results);
    }

    private function resolveIntervalMinutes(Carbon $start, Carbon $end)
    {
        $minutes = $start->diffInMinutes($end);

        if ($minutes <= 120) {          // up to 2 hours
            return 1;
        } elseif ($minutes <= 240) {    // up to 4 hours
            return 10;
        } elseif ($minutes <= 1440) {   // up to 1 day
            return 30;
        } else {                        // more than 1 day
            return 60;
        }
    }


    public function wifiClientGraph(Request $request)
    {
        $start = Carbon::createFromTimestampMs($request->get('startDate'));
        $end   = Carbon::createFromTimestampMs($request->get('endDate'));

        $interval = $this->resolveIntervalMinutes($start, $end);

        $user = Auth::user();
        if ($user->parent_id) {
            $user = User::find($user->parent_id);
        }
        $userId = $user->id;

        // Routers under this PDO
        $routers = DB::table('routers')
            ->leftJoin('locations', 'routers.location_id', '=', 'locations.id')
            ->where('pdo_id', $userId)
            ->select('routers.id', 'routers.name', 'locations.name as location_name')
            ->get();

        $routerId = $request->get('wifi_router_id'); // can be null

        // Determine which routers we will include
        if ($routerId) {
            $routerList = collect([$routerId]);
        } else {
            $routerList = $routers->pluck('id');
        }

        // Fetch records for all routers, grouped per router + timestamp
        // $raw = DB::table('wi_fi_statuses')
        //     ->select(
        //         'wifi_router_id',
        //         DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i") as time'),
        //         DB::raw('SUM(client_2g) as client_2g'),
        //         DB::raw('SUM(client_5g) as client_5g')
        //     )
        //     ->whereIn('wifi_router_id', $routerList)
        //     ->whereBetween('created_at', [$start, $end])
        //     ->groupBy('wifi_router_id', DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:%i")'))
        //     ->orderBy('time', 'ASC')
        //     ->get();

        $raw = DB::table('wi_fi_statuses')
            ->select(
                'wifi_router_id',
                DB::raw("DATE_FORMAT(DATE_SUB(created_at, INTERVAL (MINUTE(created_at) % $interval) MINUTE), '%Y-%m-%d %H:%i') AS time"),
                DB::raw("MAX(client_2g) as client_2g"),
                DB::raw("MAX(client_5g) as client_5g")
            )
            ->whereIn('wifi_router_id', $routerList)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('wifi_router_id', 'time')
            ->orderBy('time')
            ->get();

        // Build master label list (all timestamps across all routers)
        $labels = $raw->pluck('time')->unique()->values();

        // Build response structure (one dataset per router)
        $responseRouters = [];

        foreach ($routerList as $rid) {

            $routerRows = $raw->where('wifi_router_id', $rid)->keyBy('time');

            $data_total = [];
            $data_2g    = [];
            $data_5g    = [];

            foreach ($labels as $time) {
                if (isset($routerRows[$time])) {
                    $row = $routerRows[$time];
                    $data_2g[]    = (int) $row->client_2g;
                    $data_5g[]    = (int) $row->client_5g;
                    $data_total[] = (int) $row->client_2g + (int) $row->client_5g;
                } else {
                    // No data for this router at this timestamp
                    $data_2g[]    = 0;
                    $data_5g[]    = 0;
                    $data_total[] = 0;
                }
            }

            $routerName = optional($routers->firstWhere('id', $rid))->name ?? ("Router " . $rid);

            $responseRouters[] = [
                'router_id'   => $rid,
                'router_name' => $routerName,
                'total'       => $data_total,
                'client_2g'   => $data_2g,
                'client_5g'   => $data_5g
            ];
        }

        return response()->json([
            'interval_minutes' => $interval,
            'labels'  => $labels->values(),
            'routers' => $responseRouters
        ]);
    }
}
