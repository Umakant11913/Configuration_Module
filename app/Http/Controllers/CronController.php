<?php

namespace App\Http\Controllers;

use App\Events\PdoLowCreditsEvent;
use App\Events\PdoRouterAutoRenewEvent;
use App\Events\PdoRouterDownEvent;
use App\Events\PdoRouterOverLoadEvent;
use App\Events\PdoRouterUpEvent;
use App\Events\PdoSendAdsSummaryEvent;
use App\Events\PdoSmsQuotaEvent;
use App\Events\PdoSubscriptionEndAlertEvent;
use App\Models\Banners;
use App\Models\CreditsHistoy;
use App\Models\Distributor;
use App\Models\InternetPlan;
use App\Models\Location;
use App\Models\Models;
use App\Models\NotificationSettings;
use App\Models\PdoaPlan;
use App\Models\PdoSmsQuota;
use App\Models\PdoCredits;
use App\Models\Router;
use App\Models\RouterLastOnline;
use App\Models\User;
use App\Models\RouterNotification;
use App\Models\UserImpression;
use App\Models\WiFiOrders;
use App\Models\WiFiStatus;
use Carbon\Carbon;
use com\zoho\crm\api\notification\Notification;
use Dflydev\DotAccessData\Data;
use Exception;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use function PHPUnit\Framework\isEmpty;

class CronController extends Controller
{
    /**
     * @var null
     */

    public function routerStatusCheck()
    {

        $currentDate = Carbon::today()->format('Y-m-d H:i:s');
        $lastFiveDays = Carbon::now()->subDays(5)->format('Y-m-d H:i:s');
        $threeMinuteOldTime = Carbon::now()->subMinutes(3)->format('Y-m-d H:i:s');

        $wifiOwners = Router::where('lastOnline', ">=", $lastFiveDays)->where('lastOnline', "<", $threeMinuteOldTime)->get();
        $ownerIds = $wifiOwners->pluck('owner_id')->toArray();
        $locationIdsWithNullOwner = $wifiOwners->whereNull('owner_id')->pluck('location_id')->toArray();

        $ownerIdsFromLocations = Location::whereIn('id', $locationIdsWithNullOwner)
            ->whereNotNull('owner_id')
            ->pluck('owner_id')
            ->toArray();

        $combinedOwnerIds = array_merge($ownerIds, $ownerIdsFromLocations);

        // $customOwnerIds = [ ];

        $routersDown = Router::where(function ($query) use ($combinedOwnerIds) {
            $query->whereIn('owner_id', $combinedOwnerIds)->orWhereNull('owner_id');
        })->where('lastOnline', '>=', $lastFiveDays)->where('lastOnline', '<', $threeMinuteOldTime)->get();

        if ($routersDown->count() > 0) {
            foreach ($routersDown as $routerDown) {
                if ($routerDown->last_online_status == 'up') {
                    $routerIsDown = $routerDown->update(['last_online_status' => 'down']);
                    $routerNotification = RouterNotification::where('user_id', $routerDown->owner_id)->where('notification_type', 'router_down')->where('router_id', $routerDown->id)->first();
                    $locationName = Location::where('id', $routerDown->location_id)->first();

                    $notification = [
                        'user_id' => $routerDown->owner_id,
                        'status' => 0,
                        'router_id' => $routerDown->id,
                        'notification_type' => 'router_down'
                    ];

                    $toSend = false;
                    if (!$routerNotification) {
                        $routerNotification = RouterNotification::create($notification);
                        //$toSend = true;
                    }

                    $user = User::where('id', $routerDown->owner_id)->first();
                    $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_down')
                        ->where('frequency','on-event')->first();
                    if ($notificationSettings) {
                        event(new PdoRouterDownEvent($user, $routerDown, $notificationSettings ?? null, $locationName));
                    }
                    $routerNotification->status = 1;
                    $routerNotification->router_id = $routerDown->id;
                    $routerNotification->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $routerNotification->save();

                }
            }
        }

        DB::enableQueryLog();
        $routersUp = Router::where('lastOnline', '>=', Carbon::now()->subMinutes(3)->format('Y-m-d H:i:s'))->get();

        if ($routersUp->count() > 0) {
            foreach ($routersUp as $routerUp) {
                if ($routerUp->last_online_status == 'down' || $routerUp->last_online_status == NULL) {
                    $routerIsUp = $routerUp->update(['last_online_status' => 'up']);

                    $routerLastOnline = RouterLastOnline::where('pdo_id', $routerUp->pdo_id)->first();

                    if ($routerLastOnline) {
                        $routerLastOnline->last_online = $routerUp->lastOnline;
                        $routerLastOnline->save();
                    } else {
                        $routerLastOnlineData = [
                            'pdo_id' => $routerUp->pdo_id,
                            'last_online' => $routerUp->lastOnline,
                            'notification_type' => 'router_up'
                        ];
                        $routerLastOnline = RouterLastOnline::create($routerLastOnlineData);
                    }

                    $routerNotificationUp = RouterNotification::where('user_id', $routerUp->owner_id)->where('notification_type', 'router_up')->where('router_id', $routerUp->id)->first();
                    $locationName = Location::where('id', $routerUp->location_id)->first();

                    $routerNotificationData = [
                        'user_id' => $routerUp->owner_id,
                        'status' => 0,
                        'router_id' => $routerUp->id,
                        'notification_type' => 'router_up'
                    ];

                    $toSend = false;
                    if (!$routerNotificationUp) {
                        $routerNotificationUp = RouterNotification::create($routerNotificationData);
                        //$toSend = true;
                    }

                    $user = User::where('id', $routerUp->owner_id)->first();
                    $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_up')
                        ->where('frequency','on-event')->first();
                    if ($notificationSettings) {
                        event(new PdoRouterUpEvent($user, $routerUp, $notificationSettings ?? null, $locationName));
                    }
                    $routerNotificationUp->status = 1;
                    $routerNotificationUp->router_id = $routerUp->id;
                    $routerNotificationUp->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                    $routerNotificationUp->save();
                }
            }
        }
        return response()->json([
            'message' => 'Send Notification Successfully'
        ], 200);

    }

    public function routerOverload()
    {
        $threeMinutesAgo = now()->subMinutes(3);

        $impactedRouters = WiFiStatus::select(DB::raw('wifi_router_id'), DB::raw("max(cpu_usage) as cpu"))->where('updated_at', '>', $threeMinutesAgo)->groupBy('wifi_router_id')->having('cpu', '>', 1)->get();

        foreach ($impactedRouters as $router) {
            $routerFound = Router::where('id', $router->wifi_router_id)->first();
            if ($routerFound) {
                $locationName = Location::where('id', $routerFound->location_id)->first();
                $user = User::where('id', $routerFound->owner_id)->first();
                $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_overload')
                    ->where('frequency','on-event')->first();
                if ($user->id) {
                    if ($notificationSettings) {
                        event(new PdoRouterOverLoadEvent($user, $routerFound, $router, $notificationSettings ?? null, $locationName));
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Send Notification Successfully'
        ], 200);
    }

    // Job for Sending Alert to PDO for not having enough Credits

    public function checkAutoRenewStatus()
    {
        try {
            $routers = Router::whereDate('auto_renewal_date', Carbon::today())->where('is_active', 1)->get();
            foreach ($routers as $router) {
                $user = User::find($router->owner_id);
                if (!$user || !$user->auto_renew_subscription || !$user->role) {
                    continue; // Skip if user is not eligible for auto-renewal
                }

                $activeRoutersCount = Router::where('owner_id', $user->id)
                    ->where('is_active', 1)
                    ->whereDate('auto_renewal_date', Carbon::today())
                    ->count();
                $activeRouters = Router::where('owner_id', $user->id)
                    ->where('is_active', 1)
                    ->whereDate('auto_renewal_date', Carbon::today())
                    ->get();

                $pdoPlan = PdoaPlan::find($user->pdo_type);
                $pdoCredits = PdoCredits::where('pdo_id', $user->id)->first();
                $smsQuota = PdoSmsQuota::where('pdo_id', $user->id)->first();
                $validity_expiry_date = Carbon::parse($pdoCredits->expiry_date);
                //$grace_period_date = $validity_expiry_date->copy()->addMonths($pdoPlan->grace_period);
                $grace_period_date = $validity_expiry_date->copy()->addDays($pdoPlan->grace_period);
                $grace_period = $grace_period_date->format('Y-m-d');

                //If lies in validity period
                if ($validity_expiry_date >= today()) {

                    if ($pdoCredits) {
                        $pendingCredits = $pdoCredits->credits - $pdoCredits->used_credits;
                        // Check if there are sufficient credits equal to the number of active routers
                        if ($pendingCredits < $activeRoutersCount) {
                            // Deactivate all routers
                            foreach ($activeRouters as $activeRouter) {
                                $activeRouter->is_active = 0;
                                $activeRouter->auto_renewal_date = null;
                                $activeRouter->last_configuration_version = $activeRouters->configurationVersion;
                                $activeRouter->last_updated_at = $activeRouters->updated_at;
                                $activeRouter->increment('configurationVersion');
                                $activeRouter->save();
                                event(new PdoLowCreditsEvent($user, $activeRouter, false, true, false));
                            }
                        } else {
                            if ($pendingCredits > $activeRoutersCount) {
                                // Sufficient credits available, renew routers
//                                    for ($i = 0; $i < $active_routers; $i++) {
                                $router->auto_renewal_date = Carbon::today()->addMonth(1);
                                $router->save();
                                $usedCredits = PdoCredits::where('pdo_id', $user->id)->first();
                                $usedCredits->used_credits += 1;
                                $usedCredits->save();
                                CreditsHistoy::create([
                                    'pdo_id' => $user->id,
                                    'router_id' => $router->id,
                                    'type' => 'renewal',
                                ]);
//                                    }
                                // Handle SMS quota carry forward
                                /* if ($smsQuota) {
                                     foreach ($smsQuota as $pdo_quota) {
                                         $pdo_id = $pdo_quota->pdo_id;
                                         $sms_quota_value = $pdo_quota->sms_quota;
                                         $sms_used_value = $pdo_quota->sms_used;
                                         $add_on_sms_value = $pdo_quota->add_on_sms;

                                         $total_sms_quota = $sms_quota_value - $sms_used_value;

                                         if ($total_sms_quota < 0) {
                                             $extra_sms_used = abs($total_sms_quota);
                                             $carry_forward = $add_on_sms_value - $extra_sms_used;
                                         } else {
                                             $carry_forward = $add_on_sms_value;
                                         }
                                         $user = User::where('id', $pdo_id)->where('role', '1')->first();
                                         $pdo_plan = PdoaPlan::where('id', $user->pdo_type)->first();
                                         $active_router = Router::where('owner_id', $pdo_id)->where('is_active', '1')->count();

                                         if ($carry_forward > 0) {
                                             if ($active_router) {
                                                 $sms = $active_router * $pdo_plan->sms_quota;
                                                 $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => $sms, 'add_on_sms' => $carry_forward];
                                                 PdoSmsQuota::create($create_sms);
                                             } else {
                                                 $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => 0, 'add_on_sms' => $carry_forward];
                                                 PdoSmsQuota::create($create_sms);
                                             }
                                         } else {
                                             $sms = $active_router * $pdo_plan->sms_quota;
                                             $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => $sms, 'add_on_sms' => 0];
                                             PdoSmsQuota::create($create_sms);
                                         }
                                     }*/
                                if ($smsQuota) {
                                    $carryForward = max(0, $smsQuota->add_on_sms - max(0, $smsQuota->sms_quota - $smsQuota->sms_used));

                                    if ($carryForward > 0) {
                                        $activeRouterCount = Router::where('owner_id', $user->id)
                                            ->where('is_active', '1')
                                            ->count();

                                        $sms = $activeRouterCount * $pdoPlan->sms_quota;
                                        PdoSmsQuota::create([
                                            'pdo_id' => $user->id,
                                            'sms_quota' => $sms,
                                            'add_on_sms' => $carryForward,
                                        ]);
                                    } else {
                                        PdoSmsQuota::create([
                                            'pdo_id' => $user->id,
                                            'sms_quota' => 0,
                                            'add_on_sms' => 0,
                                        ]);
                                    }
                                } else {
                                }
                            }
                        }
                    } else {
                        // If Pdo-Credits Not Available
                        foreach ($routers as $router) {
                            $router->is_active = 0;
                            $router->auto_renewal_date = null;
                            $router->last_configuration_version = $router->configurationVersion;
                            $router->last_updated_at= $router->updated_at;
                            $router->increment('configurationVersion');
                            $router->save();
                        }
                        event(new PdoLowCreditsEvent($user, $router, false, true, false));
                    }
                } // If lies in grace period
                elseif ($grace_period >= today()) {
                    //validity periods expired than check grace period
                    // the router under in grace periods and send mail alert everyday

                    /* if ($smsQuota) {
                         foreach ($smsQuota as $pdo_quota) {
                             $pdo_id = $pdo_quota->pdo_id;
                             $sms_quota_value = $pdo_quota->sms_quota;
                             $sms_used_value = $pdo_quota->sms_used;
                             $add_on_sms_value = $pdo_quota->add_on_sms;

                             $total_sms_quota = $sms_quota_value - $sms_used_value;

                             if ($total_sms_quota < 0) {
                                 $extra_sms_used = abs($total_sms_quota);
                                 $carry_forward = $add_on_sms_value - $extra_sms_used;
                             } else {
                                 $carry_forward = $add_on_sms_value;
                             }
                             $user = User::where('id', $pdo_id)->where('role', '1')->first();
                             $pdo_plan = PdoaPlan::where('id', $user->pdo_type)->first();
                             $active_router = Router::where('owner_id', $pdo_id)->where('is_active', '1')->count();

                             if ($carry_forward > 0) {
                                 if ($active_router) {
                                     $sms = $active_router * $pdo_plan->sms_quota;
                                     $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => $sms, 'add_on_sms' => $carry_forward];
                                     PdoSmsQuota::create($create_sms);
                                 } else {
                                     $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => 0, 'add_on_sms' => $carry_forward];
                                     PdoSmsQuota::create($create_sms);
                                 }
                             } else {
                                 $sms = $active_router * $pdo_plan->sms_quota;
                                 $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => $sms, 'add_on_sms' => 0];
                                 PdoSmsQuota::create($create_sms);
                             }
                         }*/

                    if ($pdoCredits) {

                        $pendingCredits = $pdoCredits->credits - $pdoCredits->used_credits;
                        if ($pendingCredits <= 0) {
                            $pdoCredits->grace_credits += 1;
                            $pdoCredits->save();
                        } elseif ($pendingCredits >= 0) {

                            $usedCredits = PdoCredits::where('pdo_id', $user->id)->first();
                            $usedCredits->used_credits += 1;
                            $usedCredits->save();
                        }
                    }

                    // Handle SMS quota carry forward
                    if ($smsQuota) {
                        $carryForward = max(0, $smsQuota->add_on_sms - max(0, $smsQuota->sms_quota - $smsQuota->sms_used));

                        if ($carryForward > 0) {
                            $activeRouterCount = Router::where('owner_id', $user->id)
                                ->where('is_active', '1')
                                ->count();

                            $sms = $activeRouterCount * $pdoPlan->sms_quota;
                            PdoSmsQuota::create([
                                'pdo_id' => $user->id,
                                'sms_quota' => $sms,
                                'add_on_sms' => $carryForward,
                            ]);
                        } else {
                            PdoSmsQuota::create([
                                'pdo_id' => $user->id,
                                'sms_quota' => 0,
                                'add_on_sms' => 0,
                            ]);
                        }
                    } else {
                    }
                    //send email alert
                    event(new PdoLowCreditsEvent($user, $router, false, true, true));

                } else {
                    // Both Grace Period & Validity Period Expired
                    foreach ($routers as $router) {
                        $router->is_active = 0;
                        $router->auto_renewal_date = null;
                        $router->last_configuration_version = $router->configurationVersion;
                        $router->last_updated_at= $router->updated_at;
                        $router->increment('configurationVersion');
                        $router->save();
                        $router->increment('configurationVersion');
                    }
                    event(new PdoLowCreditsEvent($user, $router, false, true, false));
                }
            }
            return response()->json([
                'message' => 'Success'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Internal Server Error'
            ], 500);
        }
    }

//    public function oldcheckAutoRenewStatus()
//    {
//        try {
//            $routers = Router::whereDate('auto_renewal_date', Carbon::today())->get();
//            foreach ($routers as $router) {
//                $user = User::where('id', $router->owner_id)
//                    ->where('auto_renew_subscription', 1)
//                    ->where('role', 1)
//                    ->first();
//
//                if (!$user) {
//                    continue;
//                }
//
//                $active_routers = Router::whereDate('auto_renewal_date', Carbon::today())->where('owner_id', $user->id)->orWhere('is_active', 1)->count();
//                $currentDateTime = Carbon::now();
//                $pdoPlan = PdoaPlan::find($user->pdo_type);
//                $newDateTime = $currentDateTime->addMonth($pdoPlan->validity_period);
//                $validity = $newDateTime->format('Y-m-d');
//
//                if ($validity >= today()) {
//                    $pdoCredits = PdoCredits::where('pdo_id', $user->id)->get();
//                    $totalAssignedCredits = 0;
//                    $totalUsedCredits = 0;
//
//                    foreach ($pdoCredits as $pdoCredit) {
//                        $totalAssignedCredits += $pdoCredit->credits;
//                        $totalUsedCredits += $pdoCredit->used_credits;
//                    }
//                    $totalLeftCredits = $totalAssignedCredits - $totalUsedCredits;
//                    /*$totalRouters = CreditsHistoy::where('pdo_id', $user->id)
//                        ->where('router_id', $router->id)
//                        ->count();*/
//
//                    if ($totalLeftCredits >= $active_routers) {
//
//                        if ($totalAssignedCredits >= $totalUsedCredits) {
//                            $router->auto_renewal_date = Carbon::today()->addMonth(1);
//                            $router->save();
//                            $usedCredits = PdoCredits::where('pdo_id', $user->id)->first();
//                            $usedCredits->used_credits = $usedCredits->used_credits !== null ? $usedCredits->used_credits + 1 : 1;
//                            $usedCredits->save();
//                            CreditsHistoy::create([
//                                'pdo_id' => $user->id,
//                                'router_id' => $router->id,
//                                'type' => 'renewal',
//                            ]);
//                            $smsQuota = PdoSmsQuota::where('pdo_id', $user->id)->first();
//
//                            if ($smsQuota) {
//                                $carryForward = max(0, $smsQuota->add_on_sms - max(0, $smsQuota->sms_quota - $smsQuota->sms_used));
//
//                                if ($carryForward > 0) {
//                                    $activeRouterCount = Router::where('owner_id', $user->id)
//                                        ->where('is_active', '1')
//                                        ->count();
//
//                                    $sms = $activeRouterCount * $pdoPlan->sms_quota;
//                                    PdoSmsQuota::create([
//                                        'pdo_id' => $user->id,
//                                        'sms_quota' => $sms,
//                                        'add_on_sms' => $carryForward,
//                                    ]);
//                                } else {
//                                    PdoSmsQuota::create([
//                                        'pdo_id' => $user->id,
//                                        'sms_quota' => 0,
//                                        'add_on_sms' => 0,
//                                    ]);
//                                }
//
//                            } else {
//
//                            }
//                        } else {
//                            $router->is_active = 0;
//                            $router->auto_renewal_date = null;
//                            $router->save();
//                            event(new PdoLowCreditsEvent($user, $router, false, true));
//                        }
//                    } else {
//                        foreach ($active_routers as $active_router) {
//                            $routers = Router::where('owner_id', $user->id)->get();
//                            foreach ($routers as $router) {
//                                $router->is_active = 0;
//                                $router->auto_renew_date = null;
//                                $router->save();
//                            }
//                        }
//                        event(new PdoLowCreditsEvent($user, $router, false, true));
//                    }
//
//                } else {
//                    $router->is_active = 0;
//                    $router->auto_renewal_date = null;
//                    $router->save();
//                    event(new PdoLowCreditsEvent($user, $router, false, true));
//
//                }
//            }
//            return response()->json([
//                'message' => 'Success'
//            ], 200);
//
//        } catch (\Exception $e) {
//            Log::error('Error in checkAutoRenewStatus: ' . $e->getMessage());
//            return response()->json([
//                'error' => 'Internal Server Error'
//            ], 500);
//        }
//    }

    public function ooldcheckAutoRenewStatus()
    {
        $totalAssignedCredits = 0;
        $totalUsedCredits = 0;
        $totalLeftCredits = 0;

        $APs = Router::whereDate('auto_renewal_date', Carbon::today())->get();

        foreach ($APs as $AP) {

            $user = User::where('id', $AP->owner_id)->where('auto_renew_subscription', 1)->where('role', 1)->first();
            $pdoaPlan = PdoaPlan::where('id', $user->pdo_type)->first();

            $currentDateTime = Carbon::now();
            if ($pdoaPlan->validity_period) {
                $newDateTime = $currentDateTime->addMonth($pdoaPlan->validity_period);
                $validity = $newDateTime->format('Y-m-d H:i:s');
            }

            if ($user && $validity) {

                $pdoaDatas = PdoCredits::where('pdo_id', $AP->owner_id)->get();

                foreach ($pdoaDatas as $pdoaData) {
                    $assignedCredits = $pdoaData->credits;
                    $leftCredits = $pdoaData->used_credits;
                }
                $totalAssignedCredits += $assignedCredits;
                $totalUsedCredits += $leftCredits;
                $totalLeftCredits = $totalAssignedCredits - $totalUsedCredits;

                $totalRouters = CreditsHistoy::where('pdo_id', $AP->owner_id)->where('router_id', $AP->id)->count();

                if ($totalLeftCredits > $totalRouters) {
                    $AP->auto_renewal_date = Carbon::today()->addMonth(1);
                    $AP->save();

                    $newPdoaData = PdoCredits::where('pdo_id', $AP->owner_id)->first();
                    $newPdoaData->used_credits += 1;
                    $newPdoaData->save();

                    $newCreditHistoryData = CreditsHistoy::create([
                        'pdo_id' => $AP->owner_id,
                        'router_id' => $AP->id,
                        'type' => 'renewal',
                    ]);
                    event(new PdoLowCreditsEvent($user, $AP, true, false));
                } else {
                    $AP->is_active = 0;
                    $AP->auto_renewal_date = NULL;
                    event(new PdoLowCreditsEvent($user, $AP, false, true));
                }
            } else {
                return response()->json([
                    'message' => 'Dont have valid validity period'
                ], 200);
            }
        }

//        $userIds = User::where('auto_renew_subscription', 1)
//            ->where('role', 1)
//            ->pluck('id');
//
//        $pdoaIds = PdoCredits::whereIn('pdo_id', $userIds)
//            ->whereDate('expiry_date', '=', now()->format('Y-m-d'))
//            ->get();
//
//        foreach ($pdoaIds as $pdoaId) {
//            $user = User::where('id', $pdoaId->pdo_id)->first();
////            $APs = Router::where('pdo_id', $pdoaId->pdo_id)->where('lastOnline', '>=', Carbon::now()->subMinutes(3)->toDateTimeString())->count();
//
//            $assignedCredits = $pdoaId->credits;
//            $usedCredits = $pdoaId->used_credits ?? 0;
//            $leftCredits = $assignedCredits - $usedCredits;
//
//            $pdoaPlan = PdoaPlan::where('id', $user->pdo_type)->first();
//
//            if ($pdoaPlan->validity_period) {
//                $currentDateTime = Carbon::now();
//                $newDateTime = $currentDateTime->addMonth($pdoaPlan->validity_period);
//                $expiryDate = $newDateTime->format('Y-m-d H:i:s');
//            } else {
//                $currentDateTime = Carbon::now();
//                $newDateTime = $currentDateTime->addMonth(18);
//                $expiryDate = $newDateTime->format('Y-m-d H:i:s');
//            }
//
//            if ($leftCredits > 0 && $leftCredits > $APs) {
//                $autoRenewCredits = PdoCredits::create([
//                    'pdo_id' => $pdoaId->pdo_id,
//                    'credits' => $leftCredits,
//                    'expiry_date' => $expiryDate,
//                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
//                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
//                ]);
//
//            } elseif ($leftCredits <= $APs) {
//                event(new PdoLowCreditsEvent($user, $leftCredits));
//                $user->auto_renew_subscription = 0;
//                $user->save();
//
//            }
//        }
    }

    // Job for Sending Alert to PDO before One Month of Subscription End Or if Credits are less than 80%
    public function subscriptionEndAlert()
    {
        $userIds = User::where('auto_renew_subscription', 0)
            ->where('role', 1)
            ->pluck('id');

        $thisMonth = date('Y-m-d');
        $nextMonth = date('Y-m-d', strtotime('+1 month'));


        $pdoaIds = PdoCredits::whereIn('pdo_id', $userIds)
            ->orWhere(function ($query) use ($thisMonth, $nextMonth) {
                $query->whereBetween('expiry_date', [$thisMonth, $nextMonth]);
            })
            ->get();

        foreach ($pdoaIds as $pdoaId) {
            $user = User::where('id', $pdoaId->pdo_id)->first();
            $APs = Router::where('pdo_id', $pdoaId->pdo_id)->where('lastOnline', '>=', Carbon::now()->subMinutes(3)->toDateTimeString())->count();

            $assignedCredits = $pdoaId->credits;
            $percentage = ($APs * 100) / $assignedCredits;
            $expiryDate = Carbon::parse($pdoaId->expiry_date)->format('Y-m-d');

            if ($percentage >= env('SUBSCRIPTION_PERCENTAGE_ALERT') && $expiryDate >= $thisMonth && $expiryDate <= $nextMonth) {

                $notificationCheck = DB::table('notifications')
                    ->where('notifiable_id', $pdoaId->pdo_id)
                    ->where('type', 'App\Mail\SendPdoSubscriptionEndNotification')
                    ->whereDate('created_at', Carbon::today())
                    ->exists();

                if (!$notificationCheck) {

                    event(new PdoSubscriptionEndAlertEvent($user, $percentage, $expiryDate));
                }
            } else if ($expiryDate >= $thisMonth && $expiryDate <= $nextMonth) {

                $notificationCheck = DB::table('notifications')
                    ->where('notifiable_id', $pdoaId->pdo_id)
                    ->where('type', 'App\Mail\SendPdoSubscriptionEndNotification')
                    ->whereDate('created_at', Carbon::today())
                    ->exists();

                if (!$notificationCheck) {
                    event(new PdoSubscriptionEndAlertEvent($user, '', $expiryDate));
                }
            } else if ($percentage >= env('SUBSCRIPTION_PERCENTAGE_ALERT')) {

                $notificationCheck = DB::table('notifications')
                    ->where('notifiable_id', $pdoaId->pdo_id)
                    ->where('type', 'App\Mail\SendPdoSubscriptionEndNotification')
                    ->whereDate('created_at', Carbon::today())
                    ->exists();

                if (!$notificationCheck) {
                    event(new PdoSubscriptionEndAlertEvent($user, $percentage, ''));
                }
            }
        }
    }

    public function resetSmsQuota()
    {
        $currentDate = Carbon::today()->format('Y-m-d H:i:s');
        $everyMonth = Carbon::now()->startOfMonth()->addDays(1)->format('Y-m-d H:i:s');

        // Fetch PDOs that were created at least 30 days ago
        $sms_quota = PdoSmsQuota::where('created_at', '<=', Carbon::now()->subDays(29))
            ->get();

        if ($sms_quota->isNotEmpty()) {
            foreach ($sms_quota as $pdo_quota) {

                $pdo_id = $pdo_quota->pdo_id;
                $sms_quota_value = $pdo_quota->sms_quota;
                $sms_used_value = $pdo_quota->sms_used ? $pdo_quota->sms_used : 0;
                $add_on_sms_value = $pdo_quota->add_on_sms;
                $default_value = $pdo_quota->default_sms;

                $total_sms_quota = $sms_quota_value - $sms_used_value;

                //Total SMS  > 0
                if ($total_sms_quota > 0) {
                    $carry_forward = $add_on_sms_value;
                } //Total SMS  < 0

                else if ($total_sms_quota < 0) {
                    $extra_sms_used = abs($total_sms_quota);
                    $lest_add_sms = $add_on_sms_value - $extra_sms_used;
                    $carry_forward = $lest_add_sms;
                }

                $user = User::where('id', $pdo_id)->where('role', '1')->first();
                $pdo_plan = PdoaPlan::where('id', $user->pdo_type)->first();
                $active_router = Router::where('owner_id', $pdo_id)->where('is_active', '1')->count();

                if ($carry_forward > 0) {
                    if ($active_router) {
                        $sms = $active_router * $pdo_plan->sms_quota;
                        $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => $sms, 'default_sms' => $default_value, 'carry_forward_sms' => $carry_forward];
                        PdoSmsQuota::create($create_sms);
                    } else {
                        $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => 0, 'default_sms' => $default_value, 'carry_forward_sms' => $carry_forward];
                        PdoSmsQuota::create($create_sms);
                    }
                } else {
                    $sms = $active_router * $pdo_plan->sms_quota;
                    $create_sms = ['pdo_id' => $pdo_id, 'sms_quota' => $sms, 'default_sms' => $default_value, 'carry_forward_sms' => 0];
                    PdoSmsQuota::create($create_sms);
                }
            }
            return response()->json([
                'message' => 'Your SMS Quota Carry Forward Successfully'
            ], 200);
        } else {
            return response()->json([
                'message' => 'No SMS quota data found for the specified period'
            ], 200);
        }
    }

    public function routerSubscriptionAutoRenewUpdate()
    {
        //get all AP whose auto renewal date is of today
        $ownerIds = Router::select('owner_id')
            ->whereDate('auto_renewal_date', '<=', Carbon::today())
            ->where('is_active', 1)
            ->groupBy('owner_id')
            ->get();


        foreach ($ownerIds as $ownerId) {
            $pdoUser = User::where('id', $ownerId->owner_id)->first();
            $pdoPlan = PdoaPlan::where('id', $pdoUser->pdo_type)->first();


            $gracePeriodValidity = $pdoPlan->grace_period;

            $pdoCreditList = PdoCredits::where('pdo_id', $ownerId->owner_id)->where('type', 'add-on')->where('expiry_date', '>=', Carbon::today()->toDateString())->whereRaw('credits - used_credits > 0');
            $underValidityPdoCredits = $pdoCreditList->orderBy('expiry_date', 'asc')->get();
            $underValidityPdoCreditsCount = $pdoCreditList->count();

            $index = 0;
            $graceStarted = false;

            foreach ($underValidityPdoCredits as $pdoCredit) {
                $creditsUsed = $pdoCredit->used_credits;
                $routerList = Router::where('owner_id', $ownerId->owner_id)->whereDate('auto_renewal_date', '<=', Carbon::today())->where('is_active', 1);
                $routersCount = $routerList->count();
                $routers = $routerList->get();

                if ($routersCount > 0) {
                    //return "1st";
                    foreach ($routers as $activeRouter) {
                        if ($creditsUsed >= $pdoCredit->credits) {
                            $pdoCredit->used_credits = $pdoCredit->credits;
                            break;
                        }
                        //$activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addMonths(1);
                        $activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addDays(1);
                        $activeRouter->save();
                        $creditsUsed++;

                        $creditHistory = new CreditsHistoy();
                        $creditHistory->pdo_id = $ownerId->owner_id;
                        $creditHistory->router_id = $activeRouter->id;
                        $creditHistory->type = "renewal";
                        $creditHistory->pdo_credits_id = $pdoCredit->id;
                        $creditHistory->credit_used = "1";
                        $creditHistory->save();


                        //SEND E-MAIL ALERT
                        $user = User::where('id', $ownerId->owner_id)->first();
                        $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_status')
                            ->where('frequency', 'on-event')->first();
                        if ($notificationSettings) {
                            event(new PdoRouterAutoRenewEvent($user, $activeRouter, $notificationSettings ?? null));
                            //Log::info("event sent");
                        }

                    }
                } else {
                    break;
                }
                $pdoCredit->used_credits = $creditsUsed;
                $pdoCredit->save();


            }

            //Double check that there is no router left with autorenewal
            $routerList = Router::where('owner_id', $ownerId->owner_id)->whereDate('auto_renewal_date', Carbon::today())->where('is_active', 1);
            $routersCount = $routerList->count();
            $routers = $routerList->get();

            if ($routersCount > 0) {

                // Either the router needs to use Grace credits, or deactivate
                $latestGracePeriod = PdoCredits::where('pdo_id', $ownerId->owner_id)->where('type', 'grace_credits')->orderBy('created_at', 'desc')->where('expiry_date', '>=', Carbon::today())->first();
                $latestAddOnCredits = PdoCredits::where('pdo_id', $ownerId->owner_id)->where('type', 'add_on')->orderBy('created_at', 'desc')->first();

                if ($latestGracePeriod) {
                    // Check if there is an expiry date in the future and no add-on credits after grace credits
                    if ($latestGracePeriod->expiry_date >= Carbon::today() && (!$latestAddOnCredits || $latestAddOnCredits->created_at < $latestGracePeriod->created_at)) {
                        // Increment existing grace credits

                        foreach ($routers as $activeRouter) {
                            if ($latestGracePeriod->expiry_date == Carbon::today()) {
                                //return "2st";
                                $activeRouter->auto_renewal_date = null;
                                $activeRouter->original_renewal_date = null;
                                $activeRouter->is_active = 0;
                                $activeRouter->save();

                                $creditHistory = new CreditsHistoy();
                                $creditHistory->pdo_id = $ownerId->owner_id;
                                $creditHistory->router_id = $activeRouter->id;
                                $creditHistory->type = "deactive";
                                $creditHistory->pdo_credits_id = $latestGracePeriod->id;
                                $creditHistory->credit_used = "0";
                                $creditHistory->save();

                                //SEND E-MAIL ALERT FOR SUBSCRIPTION END

                                $user = User::where('id', $ownerId->owner_id)->first();
                                $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_status')
                                    ->where('frequency', 'on-event')->first();
                                if ($notificationSettings) {
                                    event(new PdoSubscriptionEndAlertEvent($user, $activeRouter, $latestGracePeriod->used_credits, $latestGracePeriod->expiry_date, $notificationSettings ?? null, $grace_period = true));
                                }

                            } else {
                                //return "st";
                                $latestGracePeriod->increment('credits');
                                $latestGracePeriod->increment('used_credits');
                                if ($activeRouter->original_renewal_date === null) {
                                    $activeRouter->original_renewal_date = $activeRouter->auto_renewal_date;
                                }
                                //$activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addMonths(1);
                                $activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addDays(1);
                                $activeRouter->save();

                                $creditHistory = new CreditsHistoy();
                                $creditHistory->pdo_id = $ownerId->owner_id;
                                $creditHistory->router_id = $activeRouter->id;
                                $creditHistory->type = "renewal";
                                $creditHistory->pdo_credits_id = $latestGracePeriod->id;
                                $creditHistory->credit_used = "1";
                                $creditHistory->save();

                                //SEND E-MAIL ALERT
                                $user = User::where('id', $ownerId->owner_id)->first();
                                $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_status')
                                    ->where('frequency', 'on-event')->first();
                                if ($notificationSettings) {
                                    event(new PdoSubscriptionEndAlertEvent($user, $activeRouter, $latestGracePeriod->used_credits, $latestGracePeriod->expiry_date, $notificationSettings ?? null, $grace_period = false));
                                }


                            }
                        }

                    } else {
                        //return "3rd";
                        // Create new grace credits record
                        $newGraceCreditRecord = new PdoCredits();
                        $newGraceCreditRecord->pdo_id = $ownerId->owner_id;
                        $newGraceCreditRecord->credits = 0;
                        $newGraceCreditRecord->used_credits = 0;
                        $newGraceCreditRecord->type = 'grace_credits';
                        //$newGraceCreditRecord->expiry_date = Carbon::today()->addMonths($gracePeriodValidity);
                        $newGraceCreditRecord->expiry_date = Carbon::today()->addDays($gracePeriodValidity);
                        $newGraceCreditRecord->save();

                        foreach ($routers as $activeRouter) {
                            $newGraceCreditRecord->increment('credits');
                            $newGraceCreditRecord->increment('used_credits');
                            if ($activeRouter->original_renewal_date === null) {
                                $activeRouter->original_renewal_date = $activeRouter->auto_renewal_date;
                            }
                            //$activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addMonths(1);
                            $activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addDays(1);
                            $activeRouter->save();

                            $creditHistory = new CreditsHistoy();
                            $creditHistory->pdo_id = $ownerId->owner_id;
                            $creditHistory->router_id = $activeRouter->id;
                            $creditHistory->type = "renewal";
                            $creditHistory->pdo_credits_id = $newGraceCreditRecord->id;
                            $creditHistory->credit_used = "1";
                            $creditHistory->save();

                            //SEND E-MAIL ALERT
                            $user = User::where('id', $ownerId->owner_id)->first();
                            $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_status')
                                ->where('frequency', 'on-event')->first();
                            if ($notificationSettings) {
                                event(new PdoSubscriptionEndAlertEvent($user, $activeRouter, $latestGracePeriod->used_credits, $latestGracePeriod->expiry_date, $notificationSettings ?? null, $grace_period = false));
                            }


                        }
                    }
                } else {
                    //return "4rd";
                    // Create new grace credits record
                    $newGraceCreditRecord = new PdoCredits();
                    $newGraceCreditRecord->pdo_id = $ownerId->owner_id;
                    $newGraceCreditRecord->credits = 0;
                    $newGraceCreditRecord->used_credits = 0;
                    $newGraceCreditRecord->type = 'grace_credits';
                    //$newGraceCreditRecord->expiry_date = Carbon::today()->addMonths($gracePeriodValidity);
                    $newGraceCreditRecord->expiry_date = Carbon::today()->addDays($gracePeriodValidity);
                    $newGraceCreditRecord->save();

                    foreach ($routers as $activeRouter) {
                        $newGraceCreditRecord->increment('credits');
                        $newGraceCreditRecord->increment('used_credits');
                        if ($activeRouter->original_renewal_date === null) {
                            $activeRouter->original_renewal_date = $activeRouter->auto_renewal_date;
                        }
                        //$activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addMonths(1);
                        $activeRouter->auto_renewal_date = Carbon::parse($activeRouter->auto_renewal_date)->addDays(1);
                        $activeRouter->save();

                        $creditHistory = new CreditsHistoy();
                        $creditHistory->pdo_id = $ownerId->owner_id;
                        $creditHistory->router_id = $activeRouter->id;
                        $creditHistory->type = "renewal";
                        $creditHistory->pdo_credits_id = $newGraceCreditRecord->id;
                        $creditHistory->credit_used = "1";
                        $creditHistory->save();

                        //SEND E-MAIL ALERT THAT THE APs ARE IN GRACE PERIOD
                        $user = User::where('id', $ownerId->owner_id)->first();
                        $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_status')
                            ->where('frequency', 'on-event')->first();
                        if ($notificationSettings) {
                            event(new PdoSubscriptionEndAlertEvent($user, $activeRouter, $latestGracePeriod->used_credits, $latestGracePeriod->expiry_date, $notificationSettings ?? null, $grace_period = false));
                        }

                    }
                }

                // Mark auto_renewal_date as null and is_active as 0 if grace credits found and expiry date is today
                if ($latestGracePeriod && $latestGracePeriod->expiry_date == Carbon::today()) {
                    foreach ($routers as $router) {
                        $router->auto_renewal_date = null;
                        $router->original_renewal_date = null;
                        $router->is_active = 0;
                        $router->save();

                        $creditHistory = new CreditsHistoy();
                        $creditHistory->pdo_id = $ownerId->owner_id;
                        $creditHistory->router_id = $router->id;
                        $creditHistory->type = "deactive";
                        $creditHistory->pdo_credits_id = $latestGracePeriod->id;
                        $creditHistory->credit_used = "0";
                        $creditHistory->save();

                        //SEND E-MAIL ALERT

                        $user = User::where('id', $ownerId->owner_id)->first();
                        $notificationSettings = NotificationSettings::where('pdo_id', $user->id)->where('notification_type', 'router_status')
                            ->where('frequency', 'on-event')->first();
                        if($notificationSettings) {
                            event(new PdoSubscriptionEndAlertEvent($user, $activeRouter, $latestGracePeriod->used_credits, $latestGracePeriod->expiry_date, $notificationSettings ?? null, $grace_period = true));
                        }

                    }
                }

            }
        }

        //return "No Record Found";
        return response()->json([
            'message' => 'Success'
        ], 200);

        /*foreach ($routersActive as $routerActive) {
            //get AP's Owner (PDO)
            $user = User::find($routerActive->owner_id);
            if (!$user || !$user->auto_renew_subscription || !$user->role) {
                continue; // Skip if user is not eligible for auto-renewal
            }

            //Count the APs for each PDO
            $routers = Router::where('owner_id', $user->id)
                ->where('is_active', 1)
                ->whereDate('auto_renewal_date', Carbon::today());

            $activeRoutersCount = $routers->count();

            //get the all records for active APs
            $activeRouters = $routers->get();

            //get the PDO's plan, his credits history and addon histry and sms quota hisotry
            $pdoPlan = PdoaPlan::find($user->pdo_type);
            $pdoCredits = PdoCredits::where('pdo_id', $user->id)->where('type', null)->where('expiry_date', '>=', Carbon::today()->toDateString())->first();
            $pdoAddOnCredits = PdoCredits::where('pdo_id', $user->id)->where('type', 'add-on')->where('expiry_date', '>=', Carbon::today()->toDateString())->get();
            $pdoSmsQuota = PdoSmsQuota::where('pdo_id', $user->id)->first();


            //
            $validityExpiryCreditDate = "";
            $oldestExpiryDate = "";
            $gracePeriodDate = "";
            $gracePeriodFormatDate = "";
            $validityExpiryAddOnCreditDate = [];
            $validityExpiryAddOnGraceCreditDate = [];

            if($pdoCredits !== null){
                $validityExpiryCreditDate = Carbon::parse($pdoCredits->expiry_date);
                $gracePeriodDate = $validityExpiryCreditDate->copy()->addMonths($pdoPlan->grace_period);
                $gracePeriodFormatDate = $gracePeriodDate->format('Y-m-d');
            }

            if($pdoAddOnCredits->isNotEmpty()){
                foreach ($pdoAddOnCredits as $addOnCredit) {
                    if (Carbon::parse($addOnCredit->expiry_date)->isSameDay(Carbon::today()->toDateString()) || Carbon::parse($addOnCredit->expiry_date)->isAfter(Carbon::today()->toDateString())) {
                        $validityExpiryAddOnCreditDate[] = $addOnCredit->expiry_date;
                    }
                }

                sort($validityExpiryAddOnCreditDate);
                if (!empty($validityExpiryAddOnCreditDate)) {
                    //get the oldest expiry date
                    $oldestExpiryDate = $validityExpiryAddOnCreditDate[0];
                    //calculate grace period date
                    $gracePeriodDate = Carbon::parse($oldestExpiryDate)->copy()->addMonths($pdoPlan->grace_period);

                    //format grace period date as 'YYYY-MM-DD'
                    $gracePeriodFormatDate = $gracePeriodDate->format('Y-m-d');
                }

            }

            if(!$validityExpiryCreditDate || !$oldestExpiryDate) {
                $pdoGraceCredits = PdoCredits::where('pdo_id', $user->id)->where('type', null)->orderBy('created_at', 'desc')->first();
                $pdoGraceAddOnCredits = PdoCredits::where('pdo_id', $user->id)->where('type', 'add-on')->get();

                if($pdoGraceCredits !== null){
                    $graceExpiryCreditDate = Carbon::parse($pdoGraceCredits->expiry_date);
                    $gracePeriodDate = $graceExpiryCreditDate->copy()->addMonths($pdoPlan->grace_period);
                    $gracePeriodFormatDate = $gracePeriodDate->format('Y-m-d');
                }

                $validityExpiryAddOnGraceCreditDate = [];
                if($pdoGraceAddOnCredits->isNotEmpty()){
                    foreach ($pdoGraceAddOnCredits as $addOnGraceCredit) {
                        if (Carbon::parse($addOnGraceCredit->expiry_date)->isSameDay(Carbon::today()->toDateString()) || Carbon::parse($addOnGraceCredit->expiry_date)->isAfter(Carbon::today()->toDateString())) {
                            $validityExpiryAddOnGraceCreditDate[] = $addOnGraceCredit->expiry_date;
                        }
                    }

                    sort($validityExpiryAddOnGraceCreditDate);
                    if (!empty($validityExpiryAddOnGraceCreditDate)) {
                        // Get the oldest expiry date
                        $oldestGraceExpiryDate = $validityExpiryAddOnGraceCreditDate[0];
                        // Calculate grace period date
                        $gracePeriodDate = Carbon::parse($oldestGraceExpiryDate)->copy()->addMonths($pdoPlan->grace_period);

                        // Format grace period date as 'YYYY-MM-DD'
                        $gracePeriodFormatDate = $gracePeriodDate->format('Y-m-d');
                    }

                }

            }

            $creditHistory = CreditsHistoy::where('router_id', $routerActive->id)->where('type', 'activate')
                ->where('created_at', '>=', Carbon::now()->subDays(29))
                ->where('created_at', '<=', Carbon::now())
                ->first();


            //If lies in validity period
                if ($validityExpiryCreditDate >= today() || $oldestExpiryDate) {
                    return "under validity period";
                    $oldestRecordWithBalance = null;
                    $oldestRecordCreatedAt = null;

                    if ($pdoCredits || $pdoAddOnCredits) {

                        if($pdoCredits->credits - $pdoCredits->used_credits > 0) {
                            $pendingCredits = $pdoCredits->credits - $pdoCredits->used_credits;

                            if ($pendingCredits < $activeRoutersCount) {
                                // Deactivate all routers
                                foreach ($activeRouters as $activeRouter) {
                                    $activeRouter->is_active = 0;
                                    $activeRouter->auto_renewal_date = null;
                                    $activeRouter->increment('configurationVersion');
                                    $activeRouter->save();
                                    event(new PdoLowCreditsEvent($user, $activeRouter, false, true, false));
                                }
                            } else {
                                if ($pendingCredits > $activeRoutersCount) {
                                    $routerActive->auto_renewal_date = Carbon::today()->addMonth(1);
                                    $routerActive->save();
                                    $pdoCredits->used_credits += 1;
                                    $pdoCredits->save();
                                    CreditsHistoy::create([
                                        'pdo_id' => $user->id,
                                        'router_id' => $routerActive->id,
                                        'type' => 'renewal',
                                        'pdo_credits_id' => $pdoCredits->id,
                                        'credit_used' => '1'
                                    ]);
                                }
                            }
                        } else if($pdoAddOnCredits) {
                            if ($pdoAddOnCredits->isNotEmpty()) {
                                foreach ($pdoAddOnCredits as $credit) {
                                    $balance = $credit->credits - $credit->used_credits;

                                    if ($balance > 0 && ($oldestRecordWithBalance == null || $credit->created_at < $oldestRecordCreatedAt)) {
                                        $oldestRecordWithBalance = $credit;
                                        $oldestRecordCreatedAt = $credit->created_at;
                                    }
                                }
                            }
                        }
                    } else {
                        // If Pdo-Credits Not Available
                        Log::info('if credits not-available');
                        foreach ($activeRouters as $activeRouter) {
                            $activeRouter->is_active = 0;
                            $activeRouter->auto_renewal_date = null;
                            //$router->increment('configurationVersion');
                            $activeRouter->save();
                        }
                        event(new PdoLowCreditsEvent($user, $activeRouter, false, true, false));
                    }
                    if($oldestRecordWithBalance){
                        if(!$creditHistory){
                            $auto_renewal_date = Carbon::now()->addMonth();
                            $routerActive->auto_renewal_date = $auto_renewal_date;
                            $routerActive->save();
                            $oldestRecordWithBalance->used_credits = $oldestRecordWithBalance->used_credits + 1;
                            $oldestRecordWithBalance->save();
                            CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'renewal', 'pdo_credits_id' => $oldestRecordWithBalance->id, 'credit_used' => '1']);
                        } else {
                            CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'renewal', 'pdo_credits_id' => $oldestRecordWithBalance->id, 'credit_used' => '0']);
                        }
                    }

                } elseif ($gracePeriodFormatDate >= today()) { // If lies in grace period

                $oldestRecordWithBalance = null;
                $oldestRecordCreatedAt = null;

                //$pdoCredits = PdoCredits::where('pdo_id', $router->owner_id)->where('expiry_date', '>=', today())->get();
                $pdoCredits = PdoCredits::select('*')
                    ->selectRaw('(credits - used_credits) AS balance')
                    ->where('pdo_id', $routerActive->owner_id)
                    ->whereRaw('(credits - used_credits) > 0')
                    ->get();

                //get the oldest record
                if($pdoCredits->isNotEmpty()) {
                    foreach ($pdoCredits as $credit) {
                        $balance = $credit->credits - $credit->used_credits;

                        if ($balance > 0 && ($oldestRecordWithBalance == null || $credit->created_at < $oldestRecordCreatedAt)) {
                            $oldestRecordWithBalance = $credit;
                            $oldestRecordCreatedAt = $credit->created_at;
                        }
                    }
                } else {
                    $pdoGraceCredits = PdoCredits::select('*')
                        ->where('pdo_id', $routerActive->owner_id)
                        ->where('type','grace-credits')->where('created_at', 'desc')
                        ->first();

                    if($pdoGraceCredits) {
                        if(!$creditHistory) {
                            $pdoGraceCredits->credits = $pdoGraceCredits->credits + 1;
                            $pdoGraceCredits->used_credits = $pdoGraceCredits->used_credits + 1;
                            $pdoGraceCredits->save();

                            CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'renewal', 'pdo_credits_id' => $pdoGraceCredits->id, 'credit_used' => '1']);
                        } else {
                            CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'renewal', 'pdo_credits_id' => $pdoGraceCredits->id, 'credit_used' => '0']);

                        }
                    } else {
                        $newPdoGraceCredits = PdoCredits::create(['pdo_id' => $user->id, 'credits' => '1', 'used_credits' => '1', 'type' => 'grace_credits']);

                        CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'renewal', 'pdo_credits_id' => $newPdoGraceCredits->id, 'credit_used' => '1']);

                    }
                }

                //if oldest record found, then update the PDOCredits & Credits History table acc.
                if($oldestRecordWithBalance){
                    if(!$creditHistory){
                        $active_date = $activeRouter->updated_at;
                        $auto_renewal_date = Carbon::parse($active_date)->addMonth();
                        $activeRouter->auto_renewal_date = $auto_renewal_date;
                        //$router->increment('configurationVersion');
                        $router->save();

                        $oldestRecordWithBalance->used_credits = $oldestRecordWithBalance->used_credits + 1;
                        $oldestRecordWithBalance->save();
                        CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'activate', 'pdo_credits_id' => $oldestRecordWithBalance->id, 'credit_used' => '1']);
                    } else {
                        CreditsHistoy::create(['pdo_id' => $user->id, 'router_id' => $routerActive->id, 'type' => 'activate', 'pdo_credits_id' => $oldestRecordWithBalance->id, 'credit_used' => '0']);
                    }
                }

                //send email alert
                event(new PdoLowCreditsEvent($user, $routerActive, false, true, true));

            } else {
                // Both Grace Period & Validity Period Expired
                foreach ($routers as $router) {
                    $router->is_active = 0;
                    $router->auto_renewal_date = null;
                    //$router->increment('configurationVersion');
                    $router->save();
                }
                event(new PdoLowCreditsEvent($user, $routerActive, false, true, false));
            }
        }*/
    }

    public function updateSmsCredits()
    {
        $pdos = User::where('role', 1)->where('auto_renew_subscription', '1')->get();

        foreach ($pdos as $pdo) {
            $pdoPlan = PdoaPlan::where('id', $pdo->pdo_type)->first();
            $smsCredits = $pdoPlan->sms_quota;

            if (isset($smsCredits)) {
                $pdoSmsCredits = PdoSmsQuota::where('pdo_id', $pdo->id)->orderBy('created_at', 'desc')->first();
                $routersCount = Router::where('owner_id', $pdo->id)->where('is_active', '1')->count();

                if ($pdoSmsCredits) {
                    if ($smsCredits > 0) {
                        PdoSmsQuota::create(['pdo_id' => $pdo->id, 'sms_quota' => $routersCount * $smsCredits]);
                    }
                } else {
                    return response()->json([
                        'message' => 'Failure'
                    ], 400);
                }
            } else {
                return response()->json([
                    'message' => 'SMS Quota not set'
                ], 200);
            }


        }

        return response()->json([
            'message' => 'Success'
        ], 200);


    }

    public function sendAdsSummary()
    {
        $sendAdaSummarys = Banners::where('status', 1)->where('send_summary_report', 1)->get();

        if ($sendAdaSummarys->isEmpty()) {
            return response()->json(['message' => 'No ads to process.'], 404);
        }

        foreach ($sendAdaSummarys as $sendAdaSummary) {
            $summaryOptions = json_decode($sendAdaSummary->summary_option);

            if (is_array($summaryOptions)) {
                foreach ($summaryOptions as $summaryOption) {
                    //Log::info('Processing summary option:', ['option' => $summaryOption, 'pdo_id' => $sendAdaSummary->pdo_id]);

                    // Check for daily summary
                    $notifications = DB::table('notifications')
                        ->where('notifiable_id', $sendAdaSummary->pdo_id)
                        ->whereDate('created_at', Carbon::today())
                        ->get(['data']); // Select only the 'data' column

                    // Check if any notification's data contains the title 'Ads Expired'
                    $containsDaily = $notifications->contains(function ($notification) {
                        $data = json_decode($notification->data);
                        return isset($data->title) && $data->title === 'Ads Suspended';
                    });
                    // Call your function based on the presence of 'Ads Expired'
                    if ($containsDaily == false && $summaryOption == 'daily') {
                        // Call your function here
                        //Log::info('tue');
                        $this->sendDailySummary($sendAdaSummary);
                    }

                    // Check for weekly summary
                    $weeklyNotification = DB::table('notifications')
                        ->where('notifiable_id', $sendAdaSummary->pdo_id)
                        ->whereRaw('DAYOFWEEK(created_at) = ?', [Carbon::MONDAY])
                        ->first();

                    if (empty($weeklyNotification) && $summaryOption == 'weekly' && Carbon::now()->isDayOfWeek(Carbon::MONDAY)) {
                        $this->sendWeeklySummary($sendAdaSummary);
                    }

                    // Check for expired notification
                    $expiredExpired = $notifications->contains(function ($notification) {
                        $data = json_decode($notification->data);
                        return isset($data->title) && $data->title === 'Ads Expired';
                    });

                    //Log::info('Expired notification:', ['notification' => $expiredExpired]);

                    if ($expiredExpired == false && $summaryOption == 'expired' && Carbon::parse($sendAdaSummary->expiry_date)->isPast()) {

                        $this->sendExpiredNotification($sendAdaSummary);
                    }
                    // Check for impression limit notification
                    $expiredlimit = $notifications->contains(function ($notification) {
                        $data = json_decode($notification->data);
                        return isset($data->title) && $data->title === 'Ads impression limit Reached';
                    });

                    //Log::info('Impression limit notification:', ['notification' => $expiredlimit]);

                    if ($expiredlimit == false && $summaryOption == 'impression-limit' && $sendAdaSummary->impression_counts >= $sendAdaSummary->impressions) {
                        $this->sendImpressionLimitNotification($sendAdaSummary);
                    }
                }
            }
        }

        return response()->json(['message' => 'Summary reports processed.'], 200);

    }

    protected function sendDailySummary($banner)
    {
        // Your logic to send daily summary email
        //Log::info('Sending daily summary for banner ID: ' . $banner->id);
        // Mail::to($banner->user->email)->send(new DailySummaryMail($banner));
        $user = User::where('id', $banner->pdo_id)->first();

        if ($user) {
            $userImpression = UserImpression::where('banner_id', $banner->id)->get();
            event(new PdoSendAdsSummaryEvent($user, $userImpression, $banner));
        } else {

        }
    }

    protected function sendWeeklySummary($banner)
    {
        // Your logic to send weekly summary email
        //Log::info('Sending weekly summary for banner ID: ' . $banner->id);
        // Mail::to($banner->user->email)->send(new WeeklySummaryMail($banner));

        $user = User::where('id', $banner->pdo_id)->first();

        if ($user) {
            $userImpression = UserImpression::where('banner_id', $banner->id)->get();
            event(new PdoSendAdsSummaryEvent($user, $userImpression, $banner));
        } else {

        }
    }

    protected function sendExpiredNotification($banner)
    {
        // Your logic to send expired  email
        //Log::info('Sending expired notification for banner ID: ' . $banner->id);
        // Mail::to($banner->user->email)->send(new ExpiredNotificationMail($banner));
        $user = User::where('id', $banner->pdo_id)->first();

        if ($user) {
            $userImpression = UserImpression::where('banner_id', $banner->id)->get();
            event(new PdoSendAdsSummaryEvent($user, $userImpression, $banner));
        } else {

        }
    }

    protected function sendImpressionLimitNotification($banner)
    {
        // Your logic to send impression limit  email
        //Log::info('Sending impression limit notification for banner ID: ' . $banner->id);
        // Mail::to($banner->user->email)->send(new ImpressionLimitNotificationMail($banner));

        $user = User::where('id', $banner->pdo_id)->first();

        if ($user) {
            $userImpression = UserImpression::where('banner_id', $banner->id)->get();
            event(new PdoSendAdsSummaryEvent($user, $userImpression, $banner));
        }
    }

    public function wifiOdersUpdate()
    {

        //get all unused orders
        $wifiOrders = WiFiOrders::where('status', '1')->where('data_used', false)->orderBy('created_at', 'ASC')->get();

        $wifiOrdersExpireds = WiFiOrders::where('status', '1')->where('plan_expired', true)->orderBy('created_at', 'ASC')->get();
        if ($wifiOrdersExpireds) {
            foreach ($wifiOrdersExpireds as $wifiOrdersExpired) {
                $wifiOrdersExpired->data_used = true;
                $wifiOrdersExpired->save();
            }
        }
        //if empty return 1
        if ($wifiOrders->isEmpty()) {
            return 1;
        }
        //if not empty

        foreach ($wifiOrders as $wifiOrder) {

            // Check if user exists in radcheck table
            $radCheckUser = DB::connection('mysql2')->table('radcheck')->where('username', $wifiOrder->phone)->first();
            // Retrieve the latest record based on created_at column
            if (!$radCheckUser) {
                continue;
            }
            // Get current timestamp
            $now = date('d/m/Y');

            // Get data usage information
            $radAcctSession = DB::connection('mysql2')->table('radacct')
                ->select(
                    'username',
                    DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                    DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                )
                ->where('username', $wifiOrder->phone)
                ->where('start_date', '>=', $radCheckUser->plan_start_time)
                ->get();
            // If no data usage information found, continue to next order
            if (!$radAcctSession) {
                continue;
            }
            // Calculate total data used and assigned data limit
            if ($radAcctSession) {
                $downloads = 0;
                $uploads = 0;
                foreach ($radAcctSession as $session) {
                    // Access elements of each session
                    $downloads += $session->downloads;
                    $uploads += $session->uploads;
                    // Do something with the data...
                }
                $totalUsedData = $downloads + $uploads;
                $totalAssignData = $radCheckUser->data_limit;
                $planExpiration = date('d/m/Y', $radCheckUser->expiration_time);

                //Log::info('internet_validity-----> ' . $planExpiration);
                //Log::info('total used data--------> ' . $totalUsedData . ' MB');
                //Log::info('total assign data--------> ' . $totalAssignData . ' MB');
                //Log::info('today_date ' . $now);

                // Check if data limit exceeded

                if ($totalAssignData <= $totalUsedData || $planExpiration <= $now) {
                    // Mark WiFi order as data used if plan is not expired
                    if ($planExpiration >= $now) {

                        $wifiOrdersAddOnDatas = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)
                            ->where('add_on_type', true)->orderBy('created_at', 'ASC')->first();
                        //Log::info('wifi order add on data phone: ' . $wifiOrder->phone);

                        if ($wifiOrdersAddOnDatas != null) {
                            // Retrieve data limit from related internet plan
                            $internetPlanAddOnData = InternetPlan::find($wifiOrdersAddOnDatas->internet_plan_id);
                            $internetPlanAddOnDataLimit = $internetPlanAddOnData->data_limit;

                            //Log::info('wifi order are mark used save success ' . $wifiOrder);
                            // Update data limit and mark order as data used
                            DB::connection('mysql2')->table('radcheck')->where('username', $wifiOrdersAddOnDatas->phone)->update([
                                'data_limit' => $internetPlanAddOnDataLimit
                            ]);
                            $wifiOrdersAddOnDatas->data_used = true;
                            $wifiOrdersAddOnDatas->save();

                        } else {
                            $wifiOrder->data_used = true;
                            $wifiOrder->save();
                            // Mark WiFi order as data used because plan has expired
                            $defaultWifiOrders = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)->where('add_on_type', false)->orderBy('created_at', 'ASC')->first();
                            if ($defaultWifiOrders) {
                                //carry forward Data
                                $availableData = $totalAssignData - $totalUsedData;
                                //Log::info('carry forward data add ' . $availableData);
                                $internetPlanAddOnData = InternetPlan::find($defaultWifiOrders->internet_plan_id);
                                $bandwidth = $internetPlanAddOnData->bandwidth;
                                $session_duration = $internetPlanAddOnData->session_duration;
                                $data_limit = $internetPlanAddOnData->data_limit + $availableData;
                                $expiration_time = $internetPlanAddOnData->validity * 60;
                                $session_duration_window = 0;

                                $expiration_time = time() + $expiration_time;
                                // $plan_start_time = $radWiFiUser->plan_start_time;
                                DB::connection('mysql2')->table('radcheck')->where('username', $defaultWifiOrders->phone)->update([
                                    'expiration_time' => $expiration_time,
                                    'bandwidth' => $bandwidth,
                                    'session_duration' => $session_duration,
                                    'data_limit' => $data_limit,
                                    'session_duration_window' => $session_duration_window,
                                    'plan_start_time' => now()
                                ]);
                                //Log::info('WiFi order marked as data used.');
                            }
                        }
                    } else {
                        $wifiOrder->data_used = true;
                        $wifiOrder->save();
                        // Mark WiFi order as data used because plan has expired
                        // Handle expired add-on data
                        $wifiOrdersAddOnDataExpireds = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)
                            ->where('add_on_type', true)->orderBy('created_at', 'ASC')->get();
                        if ($wifiOrdersAddOnDataExpireds != null) {
                            foreach ($wifiOrdersAddOnDataExpireds as $wifiOrdersAddOnDataExpired) {

                                if ($now >= $planExpiration) {

                                    //Log::info('Add-on data expired for phone: ' . $wifiOrdersAddOnDataExpired->phone);
                                    $wifiOrdersAddOnDataExpired->data_used = true;
                                    $wifiOrdersAddOnDataExpired->save();
                                }
                                //Log::info('wifi order are mark used save success ' . $wifiOrdersAddOnDataExpired);
                            }
                            //Log::info('wifi order add on data are mark used save success ');
                        }
                        /* $defaultWifiOrders = WiFiOrders::where('status', '1')->where('data_used', false)->where('phone', $wifiOrder->phone)->orderBy('created_at', 'ASC')->first();
                         //Default WiFi order add data
                         if ($defaultWifiOrders != null) {
                             //carry forward Data
                             $carryForwardData = WiFiOrders::where('status', '1')->where('phone', $session->username)->where('add_on_type',false)->orderBy('created_at', 'ASC')->first();
                             $availableData = 0;
                             if($carryForwardData != null) {
                                 $availableData = $totalAssignData - $totalUsedData ;
                                 Log::info('carry forward data add ' .$availableData);
                             }
                             $internetPlanAddOnData = InternetPlan::find($defaultWifiOrders->internet_plan_id);
                             $bandwidth = $internetPlanAddOnData->bandwidth;
                             $session_duration = $internetPlanAddOnData->session_duration;
                             $data_limit = $internetPlanAddOnData->data_limit + $availableData ?? 0;
                             $expiration_time = $internetPlanAddOnData->validity * 60;
                             $session_duration_window = 0;

                             $expiration_time = time() + $expiration_time;
                             // $plan_start_time = $radWiFiUser->plan_start_time;
                             DB::connection('mysql2')->table('radcheck')->where('username', $defaultWifiOrders->phone)->update([
                                 'expiration_time' => $expiration_time,
                                 'bandwidth' => $bandwidth,
                                 'session_duration' => $session_duration,
                                 'data_limit' => $data_limit,
                                 'session_duration_window' => $session_duration_window,
                                 'plan_start_time' => now()
                             ]);
                             // Mark the default WiFi order as data used and save changes
                             Log::info('WiFi order marked as data used.');
                         }*/
                    }

                } else {
                    // Calculate available data and return response
                    $availableData = $totalAssignData - $totalUsedData;
                    //Log::info('you have available data ' . $availableData);
                }
            } else {
                return response()->json(['message' => 'No radacct user found for payment id: ' . $wifiOrder->id], 200);
            }
        }
    }
    // Cron API to send notification alerts
    public function modelByInventoryManager(Request $request)
    {
        $inventoryApiUrl = env('INVENTORY_MANAGER_URL') . '/api/models/list';

        // Fetch data from API
        $response = Http::get($inventoryApiUrl)->json();

        // Validate API response
        if (!isset($response['models']) || !is_array($response['models'])) {
            Log::error('Invalid response format, models data not found.', ['response' => $response]);
            return response()->json(['message' => 'Failed to retrieve models data from API.'], 500);
        }

        $models = $response['models'];
        $portalModels = Models::all();
        // $inventoryModelIds = collect($models)->pluck('id')->toArray(); // this line not use
        $portalModelIds = collect($portalModels)->pluck('inventory_model_id')->toArray();

        $portalModelIds = array_filter($portalModelIds, function($value) {
            return !is_null($value);
        });
        foreach ($models as $model) {
            // Ensure $model is an ID, not an array
            if (is_object($model) && isset($model->id)) {
                $modelId = $model->id;
            } elseif (is_array($model) && isset($model['id'])) {
                $modelId = $model['id'];
            } else {
                Log::error('Expected object or array with "id", got:', ['model' => $model]);
                continue; // Skip invalid model
            }
            // Now compare modelId with portalModelIds
            if (in_array($modelId, $portalModelIds)) {
                //dd($portalModelIds); // Debugging log to check the data
                // $this->installNewModel($models);
            }
        }
        $this->installNewModel($models);
            //  If a model exists in both Inventory and Portal and is assigned to a router, mark it as suspended
            foreach ($portalModels as $portalModel) {
                try {
                    if ($portalModel) {
                        //dd($portalModelIds); // Debugging log to check the data
                        $this->updateOrDeleteModel($portalModel['id']);
                    }
                } catch (\Exception $e) {
                    Log::error('Error updating model status', ['model_id' => $portalModel['id'], 'error' => $e->getMessage()]);
                }
            }
            Log::info('Model sync completed successfully.');
            return response()->json(['message' => 'Model sync completed successfully.'],status: 200);
   }
    public function installNewModel($models)
    {
        try {
            // Get all inventory model IDs from the received data
            $inventoryModelIds = collect($models)->pluck('id')->toArray();

            // Find all models in the portal that are NOT in the inventory anymore
            $modelsToDelete = Models::whereNotIn('inventory_model_id', $inventoryModelIds)->get();

            foreach ($modelsToDelete as $model) {
                $routerExists = Router::where('model_id', $model)->exists();
                if ($routerExists) {
                    // If a router is assigned, update model status to "suspend"
                    $model->suspend = 'suspend';
                    $model->save();
                } else {
                 $model->delete();
                }

            }
            // Loop through each inventory model to update or insert in the portal
            foreach ($models as $model) {
                $existingModel = Models::where('inventory_model_id', $model['id'])->first();

                if ($existingModel) {
                    // Update existing model
                    $existingModel->name = $model['name'];
                    $existingModel->firmware_version = $model['firmware_version'];
                    $existingModel->last_firmware_updated_at = $model['last_firmware_updated_at'];
                    $existingModel->firmware_released_at = $model['firmware_released_at'];
                    $existingModel->updated_at = $model['updated_at'];
                    $existingModel->settings = json_encode($model['settings']);
                    $existingModel->save();
                } else {
                    // Insert new model
                    $newModel = new Models();
                    $newModel->inventory_model_id = $model['id'];
                    $newModel->name = $model['name'];
                    $newModel->firmware_version = $model['firmware_version'];
                    $newModel->last_firmware_updated_at = $model['last_firmware_updated_at'];
                    $newModel->firmware_released_at = $model['firmware_released_at'];
                    $newModel->created_at = $model['created_at'];
                    $newModel->updated_at = $model['updated_at'];
                    $newModel->settings = json_encode($model['settings']);
                    $newModel->save();
                }
            }

            return response()->json([
                'message' => 'Models synced successfully',
                'status' => 'synced'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error syncing models', ['error' => $e->getMessage()]);
        }
    }

// ✅ Improved updateOrDeleteModel function
    public function updateOrDeleteModel($portalModel)
    {
        try {
            if (is_numeric($portalModel)) {
                $portalModel = Models::find($portalModel);
            }
            if (!$portalModel) {
                return response()->json([
                    'message' => 'Model ID not found',
                    'model_id' => $portalModel
                ], 404);
            }

            $routerExists = Router::where('model_id', $portalModel->id)->exists();

            if ($routerExists) {
                $portalModel->suspend = 'suspend';
                $portalModel->save();
            } else {
                $portalModel->status = 0;
                $portalModel->suspend = null;
                $portalModel->save();
            }
            return response()->json([
                'message' => 'Model status updated to suspend successfully',
                'model_id' => $portalModel,
                'status' => 'inactive and suspend'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating/deleting model', [
                'model_id' => $portalModel->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

/*    public function syncRouterInventory(Request $request)
    {
        $inventoryApiUrl = env('INVENTORY_MANAGER_URL') . '/api/inventory/list';
        $response = Http::get($inventoryApiUrl)->json();

        Log::info('Inventory Manager Response:', ['response' => $response]);
        //dd($response);
        if (isset($response['inventory']) && is_array($response['inventory'])) {
            $inventoryList = $response['inventory'];
        } else {
            Log::error('Invalid response format, inventory data not found or incorrect.', ['response' => $response]);
            return response()->json(['message' => 'Failed to retrieve inventory data from API.'], 500);
        }
       // return $inventoryList;
        $portalInventory = Router::all();
        $inventoryIds = collect($inventoryList)->pluck('id')->toArray();
        $portalInventoryIds = collect($portalInventory)->pluck('inventory_id')->toArray();
        $portalInventoryIds = array_filter($portalInventoryIds, fn($value) => !is_null($value));
        //dd($inventoryIds);
        foreach ($inventoryList as $inventory) {
            $inventoryId = $inventory['id'] ?? null;
             //dd($inventoryId);
            if ($inventoryId) {
                $this->installNewInventory($inventoryList);
            }
        }
        return response()->json([
            'message' => 'Inventory syncs completed successfully.'
        ], 200);
    }*/
    public function RouterInventoryList(Request $request)
    {        
        $portalInventory = Router::where(function($query) {
            $query->whereNull('inventory_id')
                  ->orWhere('inventory_id', 0);
        })
        ->join('models', 'routers.model_id', '=', 'models.id')
            ->select(
                'routers.name',
                'routers.mac_address',
                'routers.eth1',
                'routers.wireless1',
                'routers.wireless2',
                'routers.macAddress',
                'routers.secret',
                'routers.key',
                'routers.serial_number',
                'routers.upload_speed',
                'routers.download_speed',
                'models.name as model_name')
            ->get();
        if (!isset($portalInventory)) {
            return response()->json(['message' => 'Failed to retrieve inventory data List.','status' => false,], 500);
        } else {
            return response()->json([
                'message' => 'Inventory sync completed successfully.',
                'status' => true,
                'routers' => $portalInventory
            ], 200);
        }
    }
    public function syncRouterInventory(Request $request)
    {
        $portal_url = env('OWN_URL');
        // Fetch client key and secret from env
        $clientKey = env('CLIENT_KEY');
        $clientSecret = env('CLIENT_SECRET');

        // Build API request parameters
        $params = [];
        if (!empty($clientKey) && !empty($clientSecret)) {
            $params = [
                'client_key' => $clientKey,
                'client_secret' => $clientSecret,
                'portal_url' => $portal_url
            ];
        } else {
            $params = [
                'portal_url' => $portal_url
            ];
        }

        // Make API request with or without parameters
        $inventoryApiUrl = env('INVENTORY_MANAGER_URL') . '/api/inventory/list';
        $response = Http::get($inventoryApiUrl, $params)->json();

        // Log::info('Inventory Manager Response:', ['response' => $response]);

        if (isset($response['inventory']) && is_array($response['inventory'])) {
            $inventoryList = $response['inventory'];
        } else {
            Log::error('Invalid response format, inventory data not found or incorrect.', ['response' => $response]);
            return response()->json(['message' => 'Failed to retrieve inventory data from API.'], 500);
        }

        $portalInventory = Router::all();
        $inventoryIds = collect($inventoryList)->pluck('id')->toArray();
        $portalInventoryIds = collect($portalInventory)->pluck('inventory_id')->filter()->toArray();

        // foreach ($inventoryList as $inventory) {
        //     $inventoryId = $inventory['id'] ?? null;
        //     if ($inventoryId) {
                $this->installNewInventory($inventoryList);
        //     }
        // }

        return response()->json([
            'message' => 'Inventory sync completed successfully.'
        ], 200);
    }

    public function installNewInventory($inventoryList)
    {
        try {
            $inventoryIds = collect($inventoryList)->pluck('id')->toArray();
            // Fetch routers that are not in the new inventory list
            $routers = Router::whereNotIn('inventory_id', $inventoryIds)->get();
            foreach ($routers as $router) {
                $clientExists = Router::where('inventory_id', $router->inventory_id)->exists();
                if ($clientExists) {
                    // Just update status to 0 instead of deleting
                    $router->status = 0;
                    $router->save();
                }
            }
            // Delete routers that have NULL owner_id and are not in the new inventory list
            Router::whereNotIn('inventory_id', $inventoryIds)
                  ->whereNull('owner_id')// Added to match your previous condition
                 ->delete();
            foreach ($inventoryList as $inventory) {
                // $existingInventory = Router::where('inventory_id', $inventory['id'])->first();
                $existingInventory = Router::where('mac_address', $inventory['mac_address'])->first();

                // Find model by name, fallback to null if not found
                $model = Models::where('name', $inventory['model_name'] ?? '')->first();                
                $modelId = $model ? $model->id : null;

                if ($existingInventory) {
                    $existingInventory->inventory_id  = $inventory['id'];
                    $existingInventory->name      = $inventory['name'];
                    // $existingInventory->model_id      = $inventory['model_id'];
                    $existingInventory->model_id      = $modelId;
                    $existingInventory->modelNumber   = $inventory['model_number'];
                    $existingInventory->eth1          = $inventory['eth1'];
                    $existingInventory->wireless1     = $inventory['wireless1'];
                    $existingInventory->wireless2     = $inventory['wireless2'];
                    $existingInventory->status        = $inventory['status'];
                    $existingInventory->upload_speed  = $inventory['upload_speed'] ?? 0;
                    $existingInventory->download_speed= $inventory['download_speed'] ?? 0;
                    $existingInventory->serial_number = $inventory['serial_number'];
                    $existingInventory->macAddress   = $inventory['mac_address'];
                    $existingInventory->mac_address   = $inventory['mac_address'];
                    $existingInventory->batch_no   = $inventory['batch_no'];
                    $existingInventory->inventory_type   = $inventory['inventory_type'];
                    $existingInventory->updated_at    = now();

                    $existingInventory->save();
                    Log::info('Inventory Manager update :'. $inventory['serial_number']);
                } else {
                    $key = Str::random(20);
                    $secret = Str::random(30);
                    $newInventory = new Router();
                    $newInventory->inventory_id  = $inventory['id'];
                    $newInventory->name          = $inventory['name'];
                    // $newInventory->model_id      = $inventory['model_id'];
                    $newInventory->model_id      = $modelId;
                    $newInventory->modelNumber   = $inventory['model_number'];
                    $newInventory->eth1          = $inventory['eth1'];
                    $newInventory->wireless1     = $inventory['wireless1'];
                    $newInventory->wireless2     = $inventory['wireless2'];
                    $newInventory->status        = $inventory['status'];
                    $newInventory->upload_speed  = $inventory['upload_speed'];
                    $newInventory->download_speed= $inventory['download_speed'];
                    $newInventory->key           = $key;
                    $newInventory->secret        = $secret;
                    $newInventory->serial_number = $inventory['serial_number'];
                    $newInventory->macAddress   = $inventory['mac_address'] ?? 'SS-A4-45-44-54-54';
                    $newInventory->mac_address   = $inventory['mac_address'] ?? 'SS-A4-45-44-54-54';
                    $newInventory->batch_no   = $inventory['batch_no'];
                    $newInventory->inventory_type   = $inventory['inventory_type'];
                    $newInventory->created_at    = now();
                    $newInventory->updated_at    = now();
                    $newInventory->save();
                    Log::info('Inventory Manager insert :'. $inventory['serial_number']);
                }
            }
            return response()->json([
                'message' => 'Inventory synced successfully',
                'status' => 'synced'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error syncing inventory', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error syncing inventory', 'error' => $e->getMessage()], 500);
        }
    }
}
