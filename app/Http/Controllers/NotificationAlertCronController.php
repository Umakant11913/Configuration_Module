<?php

namespace App\Http\Controllers;

use App\Events\SendAccountChangesEmailAlertEvent;
use App\Events\SendApWiseReportEmailAlertEvents;
use App\Events\SendConfigChangesEmailAlertEvent;
use App\Events\SendfirmwareExecutionEmailAlertEvents;
use App\Events\SendFirmwareFailureEmailAlertEvent;
use App\Events\SendFirmwareSuccessEmailAlertEvent;
use App\Events\SendfirmwareUpdateEmailAlertEvents;
use App\Events\SendLocationEmailAlertEvent;
use App\Events\SendPayoutEmailAlertEvent;
use App\Events\SendPayoutReadyEmailAlertEvents;
use App\Events\SendPlanPurchaseEmailAlertEvent;
use App\Events\SendRouterDownEmailAlertEvent;
use App\Events\SendRouterOverLoadEmailAlertEvent;
use App\Events\SendRouterSlowNetworkEmailAlertEvent;
use App\Events\SendRouterUpEmailAlertEvent;
use App\Events\SendUserReportEmailAlertEvents;
use App\Mail\SendApWiseReportEmailAlerts;
use App\Models\InternetPlan;
use App\Models\Location;
use App\Models\ModelFirmwares;
use App\Models\Models;
use App\Models\NotificationSettings;
use App\Models\PayoutLog;
use App\Models\Router;
use App\Models\RouterNotification;
use App\Models\User;
use App\Models\WiFiOrders;
use App\Models\WiFiStatus;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationAlertCronController extends Controller
{

    function sendNotificationAlerts()
    {
        // Fetch notification data
        $notifications = NotificationSettings::where('status', 1)->get(); // Get all active notifications
        foreach ($notifications as $notification) {
//            Log::info('Notification data:' . $notification); // Log the entire notification data
            // Extract notification details
            $type = $notification['notification_type'] ?: '';
            $status = $notification['status'] ?: '';
            // Ensure notification is active
            if ($status !== 1) {
                continue; // Skip inactive notifications
            }
            // Handle notification based on type
            switch ($type) {
                case 'payout':
                    $this->handlePayoutNotification($notification);
                    break;
                case 'location':
                    $this->handleLocationAssignedNotification($notification);
                    break;
                case 'router_up':
                    $this->handleRouterUpNotification($notification);
                    break;
                case 'router_down':
                    $this->handleRouterDownNotification($notification);
                    break;
                case 'router_overload':
                    $this->handleRouterOverloadNotification($notification);
                    break;
                case 'router_activate':
                    $this->handleRouterActivateNotification($notification);
                    break;
                case 'configuration_change':
                    $this->handleConfigChangeIssueNotification($notification);
                    break;
                case 'slow_network':
                    $this->handleSlowNetwork($notification);
                    break;
                case 'firmware_update':
                    $this->handleFirmwareUpdateNotification($notification);
                    break;
                case 'firmware_execution':
                    $this->handleFirmwareExecutionNotification($notification);
                    break;
                case 'firmware_success':
                    $this->handleFirmwareSuccessNotification($notification);
                    break;
                case 'plan_purchase':
                    $this->handlePlanPurchase($notification);
                    break;
                case 'account_changes':
                    $this->handleAccountChanges($notification);
                    break;
                case 'payout_ready':
                    $this->handlePayoutReady($notification);
                    break;
                case 'data_consumed':
                    $this->handleDataConsumed($notification);
                    break;
                case 'User_Wise':
                    $this->handleUserWise($notification);
                    break;
                case 'ap_wise':
                    $this->handleApWise($notification);
                    break;
                default:
                    // Handle unknown notification types
                    $this->logUnknownNotification($type);
            }
        }
    }

    private function handlePayoutNotification($notification)
    {
        // Frequency check logic
        // Get current time and date
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time']) ? date('H:i', strtotime($notification['time'])) : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        //Log::info("Sending daily notification for type $type without specific time");
                        $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();


                    if (!$sentToday) {
                       // Log::info("Sending daily notification for type $type at $time");
                        $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            //Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            //Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            //Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            //Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            //Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        //Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleLocationAssignedNotificationold($notification)
    {   // Frequency check logic
        // Get current time and date
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = $notification['time'] ?? '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';

        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':

                if ($currentTime === $time) {
                    $recipientId = json_decode($recipientId, true);
                    $defualt_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    } else {
                        Log::info("Daily notification already sent today for type $type");
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day && $currentTime === $time) {
                    $startOfWeek = strtotime(now()->startOfWeek()); // Convert start of the week to a timestamp
                    $endOfWeek = strtotime(now()->endOfWeek());     // Convert end of the week to a timestamp
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    $recipientId = json_decode($recipientId, true);
                    $defualt_pdo = $recipientId ?: $pdo;
                    $sentThisWeek = DB::table('notifications')
                        ->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', $type)
                        ->where('frequency', $frequency)
                        ->whereDate('created_at', '>=', $startOfWeekFormatted)
                        ->whereDate('created_at', '<=', $endOfWeekFormatted)
                        ->exists();
                    if (!$sentThisWeek) {
                        Log::info("Sending weekly notification for type $type on $day at $time");
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date)); // Extract day from the date
                if (date('d') === $notificationDay && $currentTime === $time) {
                    Log::info("Checking if monthly notification was sent on $date at $time");
                    $recipientId = json_decode($recipientId, true);
                    $defualt_pdo = $recipientId ?: $pdo;
                    $sentThisMonth = DB::table('notifications')
                        ->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', $type)
                        ->whereMonth('created_at', date('m'))
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentThisMonth) {
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                if ($currentDate === $date && $currentTime === $time) {
                    $recipientId = json_decode($recipientId, true);
                    $defualt_pdo = $recipientId ?: $pdo;
                    $sentOnCustomDate = DB::table('notifications')
                        ->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', $date)
                        ->where('frequency', $frequency)
                        ->exists();
                    if (!$sentOnCustomDate) {
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            default:

        }

    }

    private function handleLocationAssignedNotification($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?? '';
        $recipientId = $notification['recipient_id'] ?? '';
        $pdo = $notification['pdo_id'] ?? '';
        $type = $notification['notification_type'] ?? '';
        switch ($frequency) {
            case 'daily-with-time':
                if ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } else if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time = '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendLocationNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;
        }
    }

    private function handleRouterUpNotification($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } else if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendRouterUpNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleRouterDownNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
//                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } else if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
//                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
//                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendRouterDownNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleRouterOverloadNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleRouterActivateNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendRouterActivateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleFirmwareUpdateNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleFirmwareExecutionNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleFirmwareSuccessNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleConfigChangeIssueNotification($notification)
    {
        // Frequency check logic
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handlePlanPurchase($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendPlanPurchase($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleAccountChanges($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = $notification['time'] ?? '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {

            case 'on-event':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;
                $sentToday = DB::table('notifications')
                    ->where('notifiable_id', $default_pdo)
                    ->where('notification_type', $type)
                    ->whereDate('created_at', today())
                    ->where('frequency', $frequency)
                    ->exists();

                if (!$sentToday) {
                    Log::info("Sending daily notification for type $type without specific time");
                    $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                }
                break;

            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === null) {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendAccountChanges($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handlePayoutReady($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendPayoutReady($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleDataConsumed($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendDataConsumed($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleUserWise($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = $notification['time'] ?? '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();
                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendUserReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function handleApWise($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendApWiseReport($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

    private function sendNotification($channel, $recipientId, $pdo, $frequency)
    {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->payoutEmail($recipientId, $pdo, $frequency);
        }
        if (in_array('sms', $channel)) {
            $this->payoutSms($recipientId, $pdo, $frequency);
        }

    }

    private function logUnknownNotification($type)
    {
        error_log("Unknown notification type: $type");
    }

    private function payoutEmail($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = is_string($recipientId) ? json_decode($recipientId, true) : $recipientId;

        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            // Ensure $pdo is included if there are other recipients
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo; // Add $pdo to the list if not present
            }
        }
        $today = date("Y-m-d");
        if ($frequency === "daily-with-time") {
            $payout = PayoutLog::where('payout_date', $today)->where('payout_status', 1)->first();
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendPayoutEmailAlertEvent($user, $payout, $frequency));
                    // Select the latest entry based on created_at
                    $latestNotification = DB::table('notifications')->where('notifiable_id', $defualt_pdo)->latest('created_at')->first();
                    if ($latestNotification) {
                        DB::table('notifications')->where('id', $latestNotification->id)->update([
                            'notification_type' => 'payout',
                            'frequency' => $frequency,
                        ]);
                    }

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $payout = PayoutLog::whereBetween('payout_date', [$startOfWeek, $endOfWeek])
                ->where('payout_status', 1)
                ->get();
            // Send email to user if amount is > 0
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendPayoutEmailAlertEvent($user, $payout, $frequency));
                    // Select the latest entry based on created_at
                    $latestNotification = DB::table('notifications')->where('notifiable_id', $defualt_pdo)->latest('created_at')->first();
                    if ($latestNotification) {
                        DB::table('notifications')->where('id', $latestNotification->id)->update([
                            'notification_type' => 'payout',
                            'frequency' => $frequency,
                        ]);
                    }

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            $payout = PayoutLog::where('payout_date', 'LIKE', $currentMonth . '%')->where('payout_status', 1)->get();
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendPayoutEmailAlertEvent($user, $payout, $frequency));
                    // Select the latest entry based on created_at
                    $latestNotification = DB::table('notifications')->where('notifiable_id', $defualt_pdo)->latest('created_at')->first();
                    if ($latestNotification) {
                        DB::table('notifications')->where('id', $latestNotification->id)->update([
                            'notification_type' => 'payout',
                            'frequency' => $frequency,
                        ]);
                    }

                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            $payout = PayoutLog::where('payout_date', 'LIKE', $currentMonth . '%')->where('payout_status', 1)->get();
            // Loop through recipients (can be either array or single value)
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendPayoutEmailAlertEvent($user, $payout, $frequency));
                    // Select the latest entry based on created_at
                    $latestNotification = DB::table('notifications')->where('notifiable_id', $defualt_pdo)->latest('created_at')->first();
                    if ($latestNotification) {
                        DB::table('notifications')->where('id', $latestNotification->id)->update([
                            'notification_type' => 'payout',
                            'frequency' => $frequency,
                        ]);
                    }

                }
            }
        }

    }

    private function payoutSms($recipientId, $pdo)
    {

        Log::info('pdo-------------> ' . $pdo);
        $defualt_pdo = $recipientId ?: $pdo;
        Log::info('recipient-------------> ' . $defualt_pdo);
        $payout = PayoutLog::where('pdo_owner_id', $pdo)->first();
        Log::info($payout);
        $today = date("Y-m-d");
        $payout->payout_status = 1;
        $payout->payout_date = $today;
        $payout->save();
        $wifi_orders = WiFiOrders::select('id')->where('status', '1')
            ->where('payout_status', '1')
            ->where('payout_calculation_date', $payout->payout_calculation_date)
            ->pluck('id')->toArray();

        // $sessions = SessionLog::whereIn('paymnent_id', $wifi_orders)->where('location_owner_id', $payout->pdo_owner_id)->get();
        // DB::table($db1Name.'.locations')->leftjoin($db2Name.'.radacct as radacct', $db1Name.'.locations.id', '=', $db2Name.'.radacct.location_id')
        $sessions = DB::table('user_session_logs')->leftjoin('locations', 'locations.id', '=', 'user_session_logs.location_id')
            ->whereIn('user_session_logs.paymnent_id', $wifi_orders)->where('user_session_logs.location_owner_id', $payout->pdo_owner_id)->get();
        foreach ($sessions as &$session) {
            $rad_session = DB::connection('mysql2')
                ->table('radacct')
                ->where('acctsessionid', $session->session_id)
                ->update(['payout_status' => 2]);
        }
        // Send email to user if amount is > 0
        if ($payout->payout_amount > 0) {

        }
        $user = User::where('id', $defualt_pdo)->first();
        return $payout;
    }

    private function sendLocationNotification($channel, $recipientId, $pdo, $frequency)
    {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->locationEmail($recipientId, $pdo, $frequency);
        }
        if (in_array('sms', $channel)) {
            $this->LocationSms($recipientId, $pdo, $frequency);
        }

    }

    private function locationEmail($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");
        if ($frequency === "daily-with-time") {
            $location = Location::whereDate('assigned_at', $today)->where('owner_id', $pdo)->get();
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendLocationEmailAlertEvent($user, $location, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'location')->latest('created_at')
                        ->update([
                            'notification_type' => 'location', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);
                }
            }


        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $location = Location::whereBetween('assigned_at', [$startOfWeek, $endOfWeek])
                ->where('owner_id', $pdo)
                ->get();
            // Send email to user if amount is > 0
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendLocationEmailAlertEvent($user, $location, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'location')->latest('created_at')
                        ->update([
                            'notification_type' => 'location', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            $location = Location::where('assigned_at', 'LIKE', $currentMonth . '%')->where('owner_id', $pdo)->first();
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendLocationEmailAlertEvent($user, $location, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'location')->latest('created_at')
                        ->update([
                            'notification_type' => 'location', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            $location = PayoutLog::where('assigned_at', 'LIKE', $currentMonth . '%')->where('owner_id', $pdo)->get();
            // Loop through recipients (can be either array or single value)
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendLocationEmailAlertEvent($user, $location, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'location')->latest('created_at')
                        ->update([
                            'notification_type' => 'location', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }
        }

    }

    private function locationSms($recipientId, $pdo, $frequency)
    {

    }

    private function sendRouterUpNotification($channel, $recipientId, $pdo, $frequency)
    {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->routerUpEmail($recipientId, $pdo, $frequency);
        }
        if (in_array('sms', $channel)) {
            $this->routerUpSms($recipientId, $pdo, $frequency);
        }

    }

    private function routerUpEmail($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");
        if ($frequency === "daily-with-time") {

            $router_up = RouterNotification::whereDate('created_at', $today)
                ->where('user_id', $pdo)
                ->where('notification_type', 'LIKE', '%router_up%')
                ->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });

            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterUpEmailAlertEvent($user, $router_up, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_up' ?? null)->latest('created_at')
                        ->update([
                            'notification_type' => 'router_up', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $router_up = RouterNotification::whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->where('user_id', $pdo)->where('notification_type', 'router_up')
                ->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });

            // Send email to user if amount is > 0
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterUpEmailAlertEvent($user, $router_up, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_up')->latest('created_at')
                        ->update([
                            'notification_type' => 'router_up', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }
        } else if ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            $router_up = RouterNotification::where('created_at', 'LIKE', $currentMonth . '%')->where('user_id', $pdo)
                ->where('notification_type', 'router_up')->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterUpEmailAlertEvent($user, $router_up, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_up')->latest('created_at')
                        ->update([
                            'notification_type' => 'router_up', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            $router_up = RouterNotification::where('created_at', 'LIKE', $currentMonth . '%')->where('user_id', $pdo)
                ->where('notification_type', 'router_up')->get()->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });
            // Loop through recipients (can be either array or single value)
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterUpEmailAlertEvent($user, $router_up, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_up')->latest('created_at')
                        ->update([
                            'notification_type' => 'router_up', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }
        }

    }

    private function routerUpSms($recipientId, $pdo, $frequency)
    {

    }

    private function sendRouterDownNotification($channel, $recipientId, $pdo, $frequency)
    {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->routerDownEmail($recipientId, $pdo, $frequency);
        }
        if (in_array('sms', $channel)) {
            $this->routerDownSms($recipientId, $pdo, $frequency);
        }

    }

    private function routerDownEmail($recipientId, $pdo, $frequency)
    {
        if (is_array($recipientId)) {
            $recipientId = json_encode($recipientId); // Convert array to JSON string
        }
        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");
        if ($frequency === "daily-with-time") {
            $router_down = RouterNotification::whereDate('created_at', $today)->where('user_id', $pdo)
                ->where('notification_type', 'router_down')
                ->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });

            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterDownEmailAlertEvent($user, $router_down, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_down' ?? null)->latest('created_at')
                        ->update([
                            'notification_type' => 'router_down', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $router_down = RouterNotification::whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->where('user_id', $pdo)->where('notification_type', 'router_down')
                ->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });
            // Send email to user if amount is > 0
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterDownEmailAlertEvent($user, $router_down, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_down')->latest('created_at')
                        ->update([
                            'notification_type' => 'router_down', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            $router_down = RouterNotification::where('created_at', 'LIKE', $currentMonth . '%')->where('user_id', $pdo)
                ->where('notification_type', 'router_down')
                ->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });

            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterDownEmailAlertEvent($user, $router_down, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_down')->latest('created_at')
                        ->update([
                            'notification_type' => 'router_down', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            $router_down = RouterNotification::where('created_at', 'LIKE', $currentMonth . '%')->where('user_id', $pdo)
                ->where('notification_type', 'router_down')
                ->get()
                ->map(function ($item) {
                    $router = Router::where('id', $item->router_id)->first();
                    $locationName = $router ? Location::where('id', $router->location_id)->value('name') : 'N/A';
                    return [
                        'status' => strtoupper($item->notification_type),
                        'router' => $router->name ?? 'N/A',
                        'location' => $locationName,
                    ];
                });

            // Loop through recipients (can be either array or single value)
            foreach ($recipientIdArray as $defualt_pdo) {
                $user = User::where('id', $defualt_pdo)->first();
                if ($user) {
                    // Send email alert for each user
                    event(new SendRouterDownEmailAlertEvent($user, $router_down, $frequency));
                    // Select the latest entry based on created_at
                    DB::table('notifications')->where('notifiable_id', $defualt_pdo)
                        ->where('notification_type', 'router_down')->latest('created_at')
                        ->update([
                            'notification_type' => 'router_down', // manually assign
                            'frequency' => $frequency // manually assign
                        ]);

                }
            }
        }

    }

    private function routerDownSms($recipientId, $pdo, $frequency)
    {

    }

    private function sendRouterOverloadNotification($channel, $recipientId, $pdo, $frequency)
    {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->routerOverLoadEmail($recipientId, $pdo, $frequency);
        }
        if (in_array('sms', $channel)) {
            $this->routerOverLoadSms($recipientId, $pdo, $frequency);
        }
    }

    private function routerOverLoadEmail($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::with('location')
            ->where('owner_id', $pdo)
            ->where('status', 1)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'location' => $item->location ? $item->location->name : 'N/A',
                    'name' => $item->name,
                ];
            });

        $router_over_load = [];
        Log::info('router_overload:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select(
                    'wifi_router_id',
                    DB::raw('MAX(cpu_usage) as max_cpu_usage')
                )
                    ->where('wifi_router_id', $router['id'])
                    ->whereDate('created_at', $today)
                    ->groupBy('wifi_router_id')
                    ->having('max_cpu_usage', '>', 1)
                    ->get()
                    ->map(function ($item) use ($router) {
                        return [
                            'location' => $router['location'],
                            'router' => $router['name'],
                        ];
                    });

                Log::info('Router Over Load -------------------> ', $wifiStatuses->toArray()); // Fixed logging

                if ($wifiStatuses->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses,
                    ];
                }
            }

            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'router_overload',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->whereDate('created_at', [$startOfWeek, $endOfWeek])
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }
        }
    }

    private function routerOverLoadSms($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->get();
        $router_over_load = [];
        Log::info('router_overload:--------------- > ');

        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->whereDate('created_at', $today)
                    ->groupBy('wifi_router_id')
                    ->having('max_cpu_usage', '>', 1)
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'router_overload',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->whereDate('created_at', [$startOfWeek, $endOfWeek])
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $router_over_load[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($router_over_load)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $router_over_load, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }
        }
    }

    private function sendFirmwareUpdateNotification($channel, $recipientId, $pdo, $frequency)
    {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->firmwareUpdateEmailAlert($recipientId, $pdo, $frequency);
        }

        if (in_array('sms', $channel)) {
            $this->firmwareUpdateSmsAlert($recipientId, $pdo, $frequency);
        }

    }

    private function                          firmwareUpdateEmailAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->get();

        Log::info('Firmware Update Email Alert:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {

            foreach ($routers as $router) {
                $model = Models::where('id', $router->model_id)->where('firmware_version', $router->firmwareVersion)->first();
                if ($model) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion !== $model->firmware_version) {
                        Log::info('Firmware Update Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendfirmwareUpdateEmailAlertEvents($user, $router, $frequency, $model));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $model = Models::where('id', $router->model_id)->where('firmware_version', $router->firmwareVersion)
                    ->whereDate('created_at', [$startOfWeek, $endOfWeek])
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($model) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion !== $model->firmware_version) {
                        Log::info('Firmware Update Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendfirmwareUpdateEmailAlertEvents($user, $router, $frequency, $model));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $model = Models::where('id', $router->model_id)->where('firmware_version', $router->firmwareVersion)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($model) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion !== $model->firmware_version) {
                        Log::info('Firmware Update Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendfirmwareUpdateEmailAlertEvents($user, $router, $frequency, $model));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    }
                }
            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $model = Models::where('id', $router->model_id)->where('firmware_version', $router->firmwareVersion)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($model) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion !== $model->firmware_version) {
                        Log::info('Firmware Update Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendfirmwareUpdateEmailAlertEvents($user, $router, $frequency, $model));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    }
                }
            }
        }
    }

    private function firmwareUpdateSmsAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->get();
        $router_over_load = [];
        Log::info('router_overload:--------------- > ');
    }

    private function sendFirmwareExecutionNotification($channel, $recipientId, $pdo, $frequency)
    {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->firmwareExecutionEmailAlert($recipientId, $pdo, $frequency);
        }

       /* if (in_array('sms', $channel)) {
            $this->firmwareExecutionSmsAlert($recipientId, $pdo, $frequency);
        }*/

    }

    private function firmwareExecutionEmailAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->whereNotNull('firmware_executed_at')
            ->get();

        Log::info('Firmware Executed Email Alert:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {

            foreach ($routers as $router) {
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                    // Check if firmware version does not match
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        }

    }

    private function firmwareExecutionSmsAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $routers = Router::where('owner_id', $pdo)->where('status', 1)->whereNotNull('firmware_executed_at')
            ->get();

        Log::info('Firmware Executed Email Alert:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {

            foreach ($routers as $router) {
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                // Check if firmware version does not match
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                if ($router) {
                    Log::info('Firmware Executed Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendfirmwareExecutionEmailAlertEvents($user, $router, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_execution',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        }

    }

    private function sendFirmwareSuccessNotification($channel, $recipientId, $pdo, $frequency)
    {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->firmwareSuccessEmailAlert($recipientId, $pdo, $frequency);
        }

//        if (in_array('sms', $channel)) {
//            $this->FirmwareSuccessSmsAlert($recipientId, $pdo, $frequency);
//        }

    }

    private function firmwareSuccessEmailAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->get();

        Log::info('Firmware Update Email Alert:--------------- > ' . $routers);
        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $wifi_status = WiFiStatus::where('wifi_router_id', $router->id)->where('latest_version', $router->firmwareVersion)->first();
                if ($wifi_status) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion == $wifi_status->latest_version) {
                        Log::info('Firmware success Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_success',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    } else {
                        Log::info('Firmware Failure Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareFailureEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'Failure',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }
                    }

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $wifi_status = WiFiStatus::where('wifi_router_id', $router->id)->where('latest_version', $router->firmwareVersion)
                    ->whereDate('created_at', [$startOfWeek, $endOfWeek])
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($wifi_status) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion == $wifi_status->latest_version) {
                        Log::info('Firmware Update Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    } else {
                        Log::info('Firmware Failure Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareFailureEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'Failure',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }
                    }

                }

            }

        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $wifi_status = WiFiStatus::where('wifi_router_id', $router->id)->where('latest_version', $router->firmwareVersion)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($wifi_status) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion == $wifi_status->latest_version) {
                        Log::info('Firmware Update Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    } else {
                        Log::info('Firmware Failure Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareFailureEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'Failure',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }
                    }
                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $wifi_status = WiFiStatus::where('wifi_router_id', $router->id)->where('latest_version', $router->firmwareVersion)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($wifi_status) {
                    // Check if firmware version does not match
                    if ($router->firmwareVersion == $wifi_status->latest_version) {
                        Log::info('Firmware success Email Alert  ----------------------------------> ');
                        foreach ($recipientIdArray as $default_pdo) {
                            $user = User::where('id', $default_pdo)->first();
                            if ($user) {
                                // Send email alert for each user with router and WiFi status data
                                event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $wifi_status));
                                // Update the latest notification entry
                                $latestNotification = DB::table('notifications')
                                    ->where('notifiable_id', $default_pdo)
                                    ->latest('created_at')
                                    ->first();

                                if ($latestNotification) {
                                    DB::table('notifications')
                                        ->where('id', $latestNotification->id)
                                        ->update([
                                            'notification_type' => 'firmware_available',
                                            'frequency' => $frequency,
                                        ]);
                                }
                            }
                        }

                    }

                } else {
                    Log::info('Firmware Failure Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareFailureEmailAlertEvent($user, $router, $frequency, $wifi_status));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'Failure',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        }
    }

    private function sendConfigChangeIssueNotification($channel, $recipientId, $pdo, $frequency)
    {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->configChangeIssueEmailAlert($recipientId, $pdo, $frequency);
        }

        if (in_array('sms', $channel)) {
            $this->configChangeIssueSmsAlert($recipientId, $pdo, $frequency);
        }

    }

    private function configChangeIssueEmailAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->get();
        Log::info('Configuration Changes Issue  Email Alert:--------------- > ' . $routers);
        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')->first();

                if ($configuration_changes) {
                    Log::info('Configuration Changes Issue  Email Alert:  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendConfigChangesEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'configuration_change',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')
                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($configuration_changes) {
                    // Check if firmware version does not match
                    Log::info('Firmware Update Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'configuration_change',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }

            }

        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($configuration_changes) {
                    // Check if firmware version does not match
                    Log::info('Firmware Update Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'configuration_change',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($configuration_changes) {
                    // Check if firmware version does not match
                    Log::info('Firmware success Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'configuration_change',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        }
    }

    private function configChangeIssueSmsAlert($recipientId, $pdo, $frequency)
    {

        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->where('status', 1)->get();
        Log::info('Configuration Changes Issue  Email Alert:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {

            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')->first();
                if ($configuration_changes) {
                    Log::info('Configuration Changes Issue  Email Alert:  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_success',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')
                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($configuration_changes) {
                    // Check if firmware version does not match
                    Log::info('Firmware Update Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_available',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }

            }

        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($configuration_changes) {
                    // Check if firmware version does not match
                    Log::info('Firmware Update Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_available',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $configuration_changes = Router::where('id', $router->id)->whereNotNull('last_updated_at')
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->get();
                // Check if any WiFiStatus entries met the condition
                if ($configuration_changes) {
                    // Check if firmware version does not match
                    Log::info('Firmware success Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $router, $frequency, $configuration_changes));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_available',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        }
    }

    private function sendPlanPurchase($channel, $recipientId, $pdo, $frequency)
    {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->sendPlanPurchaseEmailAlert($recipientId, $pdo, $frequency);
        }

//        if (in_array('sms', $channel)) {
//            $this->sendPlanPurchaseSmsAlert($recipientId, $pdo, $frequency);
//        }
    }

    private function sendPlanPurchaseEmailAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");

        if ($frequency === "daily-with-time") {
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->whereDate('created_at', $today)->whereNotNull('location_id')
                ->where('owner_id', $pdo)->get();

            Log::info('plan purchase Email Alert:--------------- > ');
            foreach ($wifOrders as $wifOrder) {
                if ($wifOrder) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPlanPurchaseEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'plan_purchase',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->where('owner_id', $pdo)->whereNotNull('location_id')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->get();
            // Send email to user if amount is > 0
            foreach ($wifOrders as $wifOrder) {
                // Check if any WiFiStatus entries met the condition
                if ($wifOrder) {
                    // Check if firmware version does not match
                    Log::info('Purchase internet plan  Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPlanPurchaseEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'plan_purchase',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }

            }

        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->where('owner_id', $pdo)->whereNotNull('location_id')
                ->where('created_at', 'LIKE', $currentMonth . '%')
                ->get();
            foreach ($wifOrders as $wifOrder) {
                // Check if any WiFiStatus entries met the condition
                if ($wifOrder) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPlanPurchaseEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'plan_purchase',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->where('owner_id', $pdo)->whereNotNull('location_id')
                ->where('created_at', 'LIKE', $currentMonth . '%')
                ->get();
            // Loop through recipients (can be either array or single value)
            foreach ($wifOrders as $wifOrder) {
                // Check if any WiFiStatus entries met the condition
                if ($wifOrder) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPlanPurchaseEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'plan_purchase',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        }

    }

    private function sendPlanPurchaseSmsAlert($recipientId, $pdo, $frequency)
    {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");

        if ($frequency === "daily-with-time") {
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->whereDate('created_at', $today)->whereNotNull('location_id')
                ->where('owner_id', $pdo)->get();
            Log::info('plan purchase Email Alert:--------------- > ');
            foreach ($wifOrders as $wifOrder) {
                if ($wifOrder) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_success',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->where('owner_id', $pdo)->whereNotNull('location_id')
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->get();
            // Send email to user if amount is > 0
            foreach ($wifOrders as $wifOrder) {
                // Check if any WiFiStatus entries met the condition
                if ($wifOrder) {
                    // Check if firmware version does not match
                    Log::info('Purchase internet plan  Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();
                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_available',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }

            }

        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->where('owner_id', $pdo)->whereNotNull('location_id')
                ->whereDate('created_at', 'LIKE', $currentMonth . '%')
                ->get();
            foreach ($wifOrders as $wifOrder) {
                // Check if any WiFiStatus entries met the condition
                if ($wifOrder) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_available',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            $wifOrders = WiFiOrders::where('payment_status','LIKE', '%paid%')->where('owner_id', $pdo)->whereNotNull('location_id')
                ->where('created_at', 'LIKE', $currentMonth . '%')
                ->get();
            // Loop through recipients (can be either array or single value)
            foreach ($wifOrders as $wifOrder) {
                // Check if any WiFiStatus entries met the condition
                if ($wifOrder) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        $internetPlan = InternetPlan::where('id', $wifOrder->internet_plan_id)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendFirmwareSuccessEmailAlertEvent($user, $wifOrder, $frequency, $internetPlan));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'firmware_available',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        }

    }

    private function sendAccountChanges($channel, $recipientId, $pdo, $frequency)
    {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->sendAccountChangesEmailAlert($recipientId, $pdo, $frequency);
        }

        if (in_array('sms', $channel)) {
            $this->sendAccountChangesSmsAlert($recipientId, $pdo, $frequency);
        }

    }

    private function sendAccountChangesEmailAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = is_string($recipientId) ? json_decode($recipientId, true) : $recipientId;
        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");

        if ($frequency === "on-event") {
            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }
        } elseif ($frequency === "daily-with-time") {

            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

            // Send email to user if amount is > 0
            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }
        }
    }

    private function sendAccountChangesSmsAlert($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = is_string($recipientId) ? json_decode($recipientId, true) : $recipientId;
        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");

        if ($frequency === "on-event") {
            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }
        } elseif ($frequency === "daily-with-time") {

            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

            // Send email to user if amount is > 0
            foreach ($recipientIdArray as $default_pdo) {
                $user = User::where('id', $default_pdo)->first();
                if ($user) {
                    // Send email alert for each user with router and WiFi status data
                    event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                    // Update the latest notification entry
                    $latestNotification = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->latest('created_at')
                        ->first();
                    if ($latestNotification) {
                        DB::table('notifications')
                            ->where('id', $latestNotification->id)
                            ->update([
                                'notification_type' => 'account_changes',
                                'frequency' => $frequency,
                            ]);
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
                 $currentMonth = date('Y-m');
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();
                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'account_changes',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
                $currentMonth = date('Y-m');
                // Loop through recipients (can be either array or single value)
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendAccountChangesEmailAlertEvent($user, $frequency));
                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();
                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'account_changes',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
        }
    }

   private function handleSlowNetwork($notification)
    {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time'])
            ? (new DateTime($notification['time']))->format('H:i')
            : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';
        // Ensure notification is active
        switch ($frequency) {
            case 'daily-with-time':
                if ($time === '') {
                    // Send notification if not sent today
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type without specific time");
                        $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($currentTime === $time) {
                    // Send notification if time matches
                    $recipientId = json_decode($recipientId, true);
                    $default_pdo = $recipientId ?: $pdo;
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if (!$sentToday) {
                        Log::info("Sending daily notification for type $type at $time");
                        $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    }
                }
                break;

            case 'weekly-with-time':
                if ($currentDayOfWeek === $day) {
                    $startOfWeek = strtotime(now()->startOfWeek());
                    $endOfWeek = strtotime(now()->endOfWeek());
                    $startOfWeekFormatted = date('Y-m-d', $startOfWeek);
                    $endOfWeekFormatted = date('Y-m-d', $endOfWeek);
                    if ($time === '') {
                        // Send notification if not sent this week
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day without specific time");
                            $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisWeek = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->where('frequency', $frequency)
                            ->whereDate('created_at', '>=', $startOfWeekFormatted)
                            ->whereDate('created_at', '<=', $endOfWeekFormatted)
                            ->exists();

                        if (!$sentThisWeek) {
                            Log::info("Sending weekly notification for type $type on $day at $time");
                            $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'monthly-with-time':
                $notificationDay = date('d', strtotime($date));
                if (date('d') === $notificationDay) {
                    if ($time === '') {
                        // Send notification if not sent this month
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date without specific time");
                            $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                        }
                    } elseif ($currentTime === $time) {
                        // Send notification if time matches
                        $recipientId = json_decode($recipientId, true);
                        $default_pdo = $recipientId ?: $pdo;
                        $sentThisMonth = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereMonth('created_at', date('m'))
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentThisMonth) {
                            Log::info("Sending monthly notification for type $type on $date at $time");
                            $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

            case 'Custom-with-Date-and-Time':
                $recipientId = json_decode($recipientId, true);
                $default_pdo = $recipientId ?: $pdo;

                if ($date && !$time) {
                    // Only date is provided, send notification if it's the correct date and hasn't been sent
                    if ($currentDate === $date) {
                        $sentOnCustomDate = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDate) {
                            Log::info("Sending custom notification for type $type on $date without specific time");
                            $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                } elseif (!$date && $time) {
                    // Only time is provided, send notification if it's the correct time and hasn't been sent today
                    $sentToday = DB::table('notifications')
                        ->where('notifiable_id', $default_pdo)
                        ->where('notification_type', $type)
                        ->whereDate('created_at', today())
                        ->where('frequency', $frequency)
                        ->exists();

                    if ($currentTime === $time && !$sentToday) {
                        Log::info("Sending custom notification for type $type at $time without specific date");
                        $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    }
                } elseif ($date && $time) {
                    // Both date and time are provided, send notification if both match and it hasn't been sent
                    if ($currentDate === $date && $currentTime === $time) {
                        $sentOnCustomDateTime = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->where('notification_type', $type)
                            ->whereDate('created_at', $date)
                            ->where('frequency', $frequency)
                            ->exists();

                        if (!$sentOnCustomDateTime) {
                            Log::info("Sending custom notification for type $type on $date at $time");
                            $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                        }
                    }
                }
                break;

        }
    }

/*    private function sendSlowNetwork($channel, $recipientId, $pdo, $frequency)
    {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->slowNetworkEmail($recipientId, $pdo, $frequency);
        }
        if (in_array('sms', $channel)) {
            $this->slowNetworkSms($recipientId, $pdo, $frequency);
        }

    }*/

/*    private function slowNetworkEmail($recipientId, $pdo, $frequency)
    {
        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::with('location')
            ->where('owner_id', $pdo)
            ->where('status', 1)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'location' => $item->location ? $item->location->name : 'N/A',
                    'name' => $item->name,
                ];
            });

        $router_over_load = [];
        Log::info('slow network:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select(
                    'wifi_router_id',
                    DB::raw('MAX(cpu_usage) as max_cpu_usage')
                )
                    ->where('wifi_router_id', $router['id'])
                    ->whereDate('created_at', $today)
                    ->groupBy('wifi_router_id')
                    ->having('network_speed', '>',150)
                    ->get()
                    ->map(function ($item) use ($router) {
                        return [
                            'location' => $router['location'],
                            'router' => $router['name'],
                        ];
                    });

                Log::info('Router slow network -------------------> ', $wifiStatuses->toArray()); // Fixed logging

                if ($wifiStatuses->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses,
                    ];
                }
            }

            Log::info('router slow network ----------------------------------> ');
            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'router_overload',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->whereDate('created_at', [$startOfWeek, $endOfWeek])
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $slow_network, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $slow_network, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }

        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($routers as $router) {
                $wifiStatus = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router->id)
                    ->where('created_at', 'LIKE', $currentMonth . '%')
                    ->groupBy('wifi_router_id')
                    ->havingRaw('MAX(cpu_usage) > ?', [1])
                    ->get();

                // Check if any WiFiStatus entries met the condition
                if ($wifiStatus->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatus
                    ];
                }
            }
            Log::info('router_router_over_load ----------------------------------> ');
            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterOverLoadEmailAlertEvent($user, $slow_network, $frequency));

                        // Update the latest notification entry
                        DB::table('notifications')->where('notifiable_id', $default_pdo)
                            ->where('notification_type', 'router_down')->latest('created_at')
                            ->update([
                                'notification_type' => 'router_overload', // manually assign
                                'frequency' => $frequency // manually assign
                            ]);
                    }
                }
            }
        }
    }*/

   /* private function isNotificationSent($recipientId, $pdo, $type, $frequency, $dateRange = []) {
        $query = DB::table('notifications')
            ->where('notifiable_id', $recipientId)
            ->where('notification_type', $type)
            ->where('frequency', $frequency);

        if (!empty($dateRange)) {
            $query->whereDate('created_at', '>=', $dateRange[0])
                ->whereDate('created_at', '<=', $dateRange[1]);
        } else {
            $query->whereDate('created_at', today());
        }

        return $query->exists();
    }*/

   /* private function handleSlowNetwork($notification) {
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i'); // Format: 24-hour time (HH:mm)
        $currentDayOfWeek = date('l'); // Full day name (e.g., "Monday")
        $frequency = $notification['frequency'] ?? '';
        $time = isset($notification['time']) ? (new DateTime($notification['time']))->format('H:i') : '';
        $date = $notification['date'] ?? '';
        $day = $notification['weekly_day'] ?? '';
        $channel = $notification['channel'] ?: '';
        $recipientId = $notification['recipient_id'] ?: '';
        $pdo = $notification['pdo_id'] ?: '';
        $type = $notification['notification_type'] ?: '';

        switch ($frequency) {
            case 'daily-with-time':
                $scheduledTime = (new DateTime($time))->format('H:i');

                if ($currentTime >= $scheduledTime && !$this->isNotificationSent($recipientId, $pdo, $type, $frequency)) {
                    Log::info("Sending notification for type $type with frequency $frequency");
                    $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    return true;
                }
                break;

            case 'weekly-with-time':
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

                if ($currentDayOfWeek === $day && $currentTime >= $time && !$this->isNotificationSent($recipientId, $pdo, $type, $frequency, [$startOfWeek, $endOfWeek])) {
                    Log::info("Sending notification for type $type with frequency $frequency");
                    $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    return true;
                }
                break;

            case 'monthly-with-time':
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');

                if ($currentDate >= $date && $currentTime >= $time && !$this->isNotificationSent($recipientId, $pdo, $type, $frequency, [$startOfMonth, $endOfMonth])) {
                    Log::info("Sending notification for type $type with frequency $frequency");
                    $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    return true;
                }
                break;

            case 'custom-time-and-date':
                if ($currentDate === $date && $currentTime >= $time && !$this->isNotificationSent($recipientId, $pdo, $type, $frequency)) {
                    Log::info("Sending notification for type $type with frequency $frequency");
                    $this->sendSlowNetwork($channel, $recipientId, $pdo, $frequency);
                    return true;
                }
                break;

            default:
                // Handle other cases or invalid frequency
                return false;
        }

        return false;
    }*/
// Function to send slow network notifications
    private function sendSlowNetwork($channel, $recipientId, $pdo, $frequency) {
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->slowNetworkEmail($recipientId, $pdo, $frequency);
        }
        /*if (in_array('sms', $channel)) {
            $this->slowNetworkSms($recipientId, $pdo, $frequency);
        }*/
    }

// Function to handle slow network email notifications
    private function slowNetworkEmail($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::with('location')
            ->where('owner_id', $pdo)
            ->where('status', 1)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'location' => $item->location ? $item->location->name : 'N/A',
                    'name' => $item->name,
                ];
            });

        $slow_network = [];

        Log::info('Slow network check:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select(
                    'wifi_router_id',
                    DB::raw('MAX(network_speed) as max_network_speed')
                )
                    ->where('wifi_router_id', $router['id'])
                    ->whereDate('created_at', $today)
                    ->groupBy('wifi_router_id')
                    ->having('max_network_speed', '>', 100)
                    ->get();

                Log::info('WiFi Status for Router ID: ' . $router['id'], $wifiStatuses->toArray());

                if ($wifiStatuses) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses,
                    ];
                }
            }

            Log::info('Final slow network data ----------------------------------> ', $slow_network);

            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        Log::info($user);
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'slow_network',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }
        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router['id'])
                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->groupBy('wifi_router_id')
                    ->havingRaw('network_speed', '>', 100)
                    ->get();

                if ($wifiStatuses->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses
                    ];
                }
            }

            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'slow_network',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');

            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router['id'])
                    ->where('created_at', 'LIKE', "$currentMonth%")
                    ->groupBy('wifi_router_id')
                    ->having('network_speed', '>', 100)
                    ->get();

                if ($wifiStatuses->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses
                    ];
                }
            }

            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'slow_network',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }
        }
    }

    private function slowNetworkSms($recipientId, $pdo, $frequency) {

        $recipientIdArray = json_decode($recipientId, true);
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");
        $routers = Router::with('location')
            ->where('owner_id', $pdo)
            ->where('status', 1)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'location' => $item->location ? $item->location->name : 'N/A',
                    'name' => $item->name,
                ];
            });

        $slow_network = [];

        Log::info('Slow network check:--------------- > ' . $routers);

        if ($frequency === "daily-with-time") {
            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select(
                    'wifi_router_id',
                    DB::raw('MAX(cpu_usage) as max_cpu_usage')
                )
                    ->where('wifi_router_id', $router['id'])
                    ->whereDate('created_at', $today)
                    ->groupBy('wifi_router_id')
                    ->having('network_speed', '>', 150)
                    ->get()
                    ->map(function ($item) use ($router) {
                        return [
                            'location' => $router['location'],
                            'router' => $router['name'],
                        ];
                    });

                Log::info('Router slow network -------------------> ', $wifiStatuses->toArray());

                if ($wifiStatuses->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses,
                    ];
                }
            }

            Log::info('Final slow network data ----------------------------------> ', $slow_network);

            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        // Send email alert for each user with router and WiFi status data
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        // Update the latest notification entry
                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'slow_network',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }
        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router['id'])
                    ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                    ->groupBy('wifi_router_id')
                    ->havingRaw('network_speed', '>', 150)
                    ->get();

                if ($wifiStatuses->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses
                    ];
                }
            }

            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'slow_network',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');

            foreach ($routers as $router) {
                $wifiStatuses = WiFiStatus::select('wifi_router_id', DB::raw('MAX(cpu_usage) as max_cpu_usage'))
                    ->where('wifi_router_id', $router['id'])
                    ->where('created_at', 'LIKE', "$currentMonth%")
                    ->groupBy('wifi_router_id')
                    ->having('network_speed', '>', 150)
                    ->get();

                if ($wifiStatuses->isNotEmpty()) {
                    $slow_network[] = [
                        'router' => $router,
                        'wifi_status' => $wifiStatuses
                    ];
                }
            }

            if (!empty($slow_network)) {
                foreach ($recipientIdArray as $default_pdo) {
                    $user = User::where('id', $default_pdo)->first();
                    if ($user) {
                        event(new SendRouterSlowNetworkEmailAlertEvent($user, $slow_network, $frequency));

                        $latestNotification = DB::table('notifications')
                            ->where('notifiable_id', $default_pdo)
                            ->latest('created_at')
                            ->first();

                        if ($latestNotification) {
                            DB::table('notifications')
                                ->where('id', $latestNotification->id)
                                ->update([
                                    'notification_type' => 'slow_network',
                                    'frequency' => $frequency,
                                ]);
                        }
                    }
                }
            }
        }

    }

    private function sendPayoutReady($channel, $recipientId, $pdo, $frequency) {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->sendPayoutReadyEmailAlert($recipientId, $pdo, $frequency);
        }

        if (in_array('sms', $channel)) {
            $this->sendPayoutReadySmsAlert($recipientId, $pdo, $frequency);
        }

    }

    private function sendPayoutReadyEmailAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");

        $payouts = PayoutLog::where('pdo_owner_id', $pdo)->where('payout_status', 0)
            ->get();

        Log::info('Payout Ready Email Alert:--------------- > ' . $payouts);

        if ($frequency === "daily-with-time") {

            foreach ($payouts as $payout) {
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($payouts as $payout) {
                // Check if firmware version does not match
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($payouts as $payout) {
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($payouts as $payout) {
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        }

    }
    private function sendPayoutReadySmsAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }

        $today = date("Y-m-d");

        $payouts = PayoutLog::where('pdo_owner_id', $pdo)->where('payout_status', 0)
            ->get();

        Log::info('Payout Ready Email Alert:--------------- > ' . $payouts);

        if ($frequency === "daily-with-time") {

            foreach ($payouts as $payout) {
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }

        } elseif ($frequency === "weekly-with-time") {
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            // Send email to user if amount is > 0
            foreach ($payouts as $payout) {
                // Check if firmware version does not match
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }
            }
        } elseif ($frequency === "monthly-with-time") {
            $currentMonth = date('Y-m');
            foreach ($payouts as $payout) {
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        } elseif ($frequency === "Custom-with-Date-and-Time") {
            $currentMonth = date('Y-m');
            // Loop through recipients (can be either array or single value)
            foreach ($payouts as $payout) {
                if ($payout) {
                    Log::info('Payout Ready Email Alert  ----------------------------------> ');
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendPayoutReadyEmailAlertEvents($user, $payout, $frequency));
                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'payout_ready',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }

                }

            }
        }

    }
    private function sendUserReport($channel, $recipientId, $pdo, $frequency) {
       Log::info('calling a function ');
        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->sendUserReportEmailAlert($recipientId, $pdo, $frequency);
        }

        /*if (in_array('sms', $channel)) {
            $this->sendUserReportSmsAlert($recipientId, $pdo, $frequency);
        }*/
    }
    private function sendUserReportEmailAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = is_array($recipientId) ? $recipientId : [$recipientId]; // Ensure $recipientId is an array

        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo]; // Initialize with $pdo if the array is empty
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo; // Add $pdo if not already in the array
            }
        }
        $today = date("Y-m-d");

        $users = User::where('parent_id', $pdo)->get();
        $allUserReports = []; // Initialize an empty array to store all reports

        if ($users->isNotEmpty()) {
            if ($frequency === "daily-with-time") {
                Log::info('User Report :--------------- > ' . $users);

                // Prepare a list of usernames (phones)
                $userPhones = $users->pluck('phone')->toArray();

                // Query radacct table for all users at once
                $userReports = DB::connection('mysql2')->table('radacct')
                    ->select(
                        'username',
                        DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                        DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                    )
                    ->whereIn('username', $userPhones) // Fetch for all user phones
                    ->whereDate('acctstarttime', $today) // Filter by today's date
                    ->get();

                // Merge all reports into a single array
                $allUserReports = $userReports->toArray();

                Log::info('Final User Report  ----------------------------------> ', $allUserReports);

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $recipientUser = User::find($default_pdo); // Find PDO user
                        if ($recipientUser) {
                            Log::info($recipientUser);

                            // Send email alert with aggregated data
                            event(new SendUserReportEmailAlertEvents($recipientUser, $allUserReports, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'User_Wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "weekly-with-time") {
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

                foreach ($users as $user) {
                    $userReport = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('username', $user->phone)
                        ->whereBetween('acctstarttime', [$startOfWeek, $endOfWeek]) // Weekly filter
                        ->get();
                    // Merge the current user's report with the main array
                    $allUserReports = array_merge($allUserReports, $userReport->toArray());
                }
                Log::info('Final User Report  ----------------------------------> ', $allUserReports);

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            event(new SendUserReportEmailAlertEvents($user, $allUserReports, $frequency));

                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'User_Wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "monthly-with-time") {
                $currentMonthStart = date('Y-m-01'); // First day of the current month
                $currentMonthEnd = date('Y-m-t');   // Last day of the current month

                foreach ($users as $user) {
                    $userReport = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('username', $user->phone)
                        ->whereBetween('acctstarttime', [$currentMonthStart, $currentMonthEnd]) // Filter by current month
                        ->get();

                    // Merge the current user's report with the main array
                    $allUserReports = array_merge($allUserReports, $userReport->toArray());
                }

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            event(new SendUserReportEmailAlertEvents($user, $allUserReports, $frequency));

                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'User_Wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "Custom-with-Date-and-Time") {
                $currentMonth = date('Y-m');
                // Loop through recipients (can be either array or single value)
                foreach ($users as $user) {
                    $userReport = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('username', $user->phone)
                        ->whereDate('acctstarttime', $currentMonth) // Filter by current month
                        ->get();

                    // Merge the current user's report with the main array
                    $allUserReports = array_merge($allUserReports, $userReport->toArray());
                }

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            event(new SendUserReportEmailAlertEvents($user, $allUserReports, $frequency));

                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'User_Wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        }

    }
    private function sendUserReportSmsAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");

        $users = User::where('parent_id', $pdo)->get();
        $allUserReports = []; // Initialize an empty array to store all reports

        if (!empty($users)) {
            if ($frequency === "daily-with-time") {
                Log::info('User Report :--------------- > ' . $users);
                foreach ($users as $user) {
                    $userReport = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('username', $user->phone)
                        ->whereDate('acctstarttime', $today) // Filter by today's date
                        ->get();

                    // Merge the current user's report with the main array
                    $allUserReports = array_merge($allUserReports, $userReport->toArray());
                }
                Log::info('Final User Report  ----------------------------------> ', $allUserReports);

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendUserReportEmailAlertEvents($user, $allUserReports, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'user_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "weekly-with-time") {
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

                foreach ($users as $user) {
                    $userReport = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('username', $user->phone)
                        ->whereBetween('acctstarttime', [$startOfWeek, $endOfWeek]) // Weekly filter
                        ->get();
                    // Merge the current user's report with the main array
                    $allUserReports = array_merge($allUserReports, $userReport->toArray());
                }
                Log::info('Final User Report  ----------------------------------> ', $allUserReports);

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            event(new SendUserReportEmailAlertEvents($user, $allUserReports, $frequency));

                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'user_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "monthly-with-time") {
                $currentMonthStart = date('Y-m-01'); // First day of the current month
                $currentMonthEnd = date('Y-m-t');   // Last day of the current month

                foreach ($users as $user) {
                    $userReport = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('username', $user->phone)
                        ->whereBetween('acctstarttime', [$currentMonthStart, $currentMonthEnd]) // Filter by current month
                        ->get();

                    // Merge the current user's report with the main array
                    $allUserReports = array_merge($allUserReports, $userReport->toArray());
                }

                if (!empty($allUserReports)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            event(new SendUserReportEmailAlertEvents($user, $allUserReports, $frequency));

                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'user_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        }

    }
    private function sendApWiseReport($channel, $recipientId, $pdo, $frequency) {

        $channel = json_decode($channel, true);
        if (in_array('email', $channel)) {
            $this->sendApWiseReportEmailAlert($recipientId, $pdo, $frequency);
        }

        /*if (in_array('sms', $channel)) {
            $this->sendApWiseReportSmsAlert($recipientId, $pdo, $frequency);
        }*/

    }
    private function sendApWiseReportEmailAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->get();
        $apWireReport = []; // Initialize an empty array to store all reports

        if ($routers) {
            if ($frequency === "daily-with-time") {
                Log::info('AP Wise Report :--------------- > ' . $routers);
                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereDate('acctstarttime', $today) // Filter by today's date
                        ->get();

                    foreach ($apReports as $report) {
                        $users = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($users) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $users->id,
                                    'name' => $users->first_name,
                                    'phone' => $users->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlertEvents($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "weekly-with-time") {
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereBetween('acctstarttime', [$startOfWeek, $endOfWeek]) // Weekly filter
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlertEvents($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "monthly-with-time") {
                $currentMonthStart = date('Y-m-01'); // First day of the current month
                $currentMonthEnd = date('Y-m-t');   // Last day of the current month

                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereBetween('acctstarttime', [$currentMonthStart, $currentMonthEnd]) // Filter by current month
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWiseReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWiseReport);
                if (!empty($apWiseReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlerts($user, $apWiseReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "Custom-with-Date-and-Time") {
                $currentMonth = date('Y-m');
                // Loop through recipients (can be either array or single value)
                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereDate('acctstarttime', $currentMonth) // Filter by current month
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlerts($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        }

    }
    private function sendApWiseReportSmsAlert($recipientId, $pdo, $frequency) {

        $recipientIdArray = $recipientId;
        if (empty($recipientIdArray)) {
            $recipientIdArray = [$pdo];
        } else {
            if (!in_array($pdo, $recipientIdArray)) {
                $recipientIdArray[] = $pdo;
            }
        }
        $today = date("Y-m-d");
        $routers = Router::where('owner_id', $pdo)->get();
        $apWireReport = []; // Initialize an empty array to store all reports

        if ($routers->isNotEmpty()) {
            if ($frequency === "daily-with-time") {
                Log::info('AP Wise Report :--------------- > ' . $routers);
                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereDate('acctstarttime', $today) // Filter by today's date
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlerts($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "weekly-with-time") {
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));

                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereBetween('acctstarttime', [$startOfWeek, $endOfWeek]) // Weekly filter
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlerts($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "monthly-with-time") {
                $currentMonthStart = date('Y-m-01'); // First day of the current month
                $currentMonthEnd = date('Y-m-t');   // Last day of the current month

                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereBetween('acctstarttime', [$currentMonthStart, $currentMonthEnd]) // Filter by current month
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlerts($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            } elseif ($frequency === "Custom-with-Date-and-Time") {
                $currentMonth = date('Y-m');
                // Loop through recipients (can be either array or single value)
                foreach ($routers as $router) {
                    $apReports = DB::connection('mysql2')->table('radacct')
                        ->select(
                            'username',
                            DB::raw('IFNULL(ceil(acctinputoctets/(1024*1024)), 0) as downloads'),
                            DB::raw('IFNULL(ceil(acctoutputoctets/(1024*1024)), 0) as uploads')
                        )
                        ->where('calledstationid', $router->mac_address)
                        ->whereDate('acctstarttime', $currentMonth) // Filter by current month
                        ->get();

                    foreach ($apReports as $report) {
                        $user = User::where('phone', $report->username)->first(); // Fetch user by username (phone)
                        if ($user) {
                            // Combine router, user, and report details
                            $apWireReport[] = [
                                'router' => [
                                    'id' => $router->id,
                                    'name' => $router->name,
                                    'mac_address' => $router->mac_address,
                                ],
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->first_name,
                                    'phone' => $user->phone,
                                ],
                                'report' => [
                                    'downloads' => $report->downloads,
                                    'uploads' => $report->uploads,
                                ],
                            ];
                        }
                    }
                }
                Log::info('Final AP Wise User Report  ----------------------------------> ', $apWireReport);
                if (!empty($apWireReport)) {
                    foreach ($recipientIdArray as $default_pdo) {
                        $user = User::where('id', $default_pdo)->first();
                        if ($user) {
                            Log::info($user);
                            // Send email alert for each user with router and WiFi status data
                            event(new SendApWiseReportEmailAlerts($user, $apWireReport, $frequency));

                            // Update the latest notification entry
                            $latestNotification = DB::table('notifications')
                                ->where('notifiable_id', $default_pdo)
                                ->latest('created_at')
                                ->first();

                            if ($latestNotification) {
                                DB::table('notifications')
                                    ->where('id', $latestNotification->id)
                                    ->update([
                                        'notification_type' => 'ap_wise',
                                        'frequency' => $frequency,
                                    ]);
                            }
                        }
                    }
                }
            }
        }

    }



}
