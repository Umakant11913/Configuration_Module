<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Http\Resources\NotificationStatusResource;
use App\Http\Resources\NotificationStatusCollection;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\NotificationSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationSettingsController extends Controller
{
    private $possibleAlertFields = [
        [
        'type'  =>  'notification[payout]',
        'title' =>  'Payout Calculated Email Alert'
        ],
        [
        'type'  =>  'notification[location]',
        'title' =>  'Location Assigned Email Alert'
        ],
        [
        'type'  =>  'notification[router_up]',
        'title' =>  'AP Router Up Email Alert'
        ],
        [
        'type'  =>  'notification[router_down]',
        'title' =>  'AP Router Down Email Alert'
        ],
        [
        'type'  =>  'notification[router_overload]',
        'title' =>  'AP Router Over Load Email Alert'
        ],
        [
        'type'  =>  'notification[router_status]',
        'title' =>  'AP Router Activate/De-activate Email Alert'
        ],
        [
        'type'  =>  'notification[configuration_change]',
        'title' =>  'AP Configuration Change Issue Email Alert'
        ],
        [
        'type'  =>  'notification[slow_network]',
        'title' =>  'AP Slow Network Email Alert'
        ],
        [
        'type'  =>  'notification[firmware_available]',
        'title' =>  'Firmware Update Available Email Alert'
        ],
        [
        'type'  =>  'notification[firmware_execution]',
        'title' =>  'Firmware Execution Email Alert'
        ],
        [
        'type'  =>  'notification[firmware_success]',
        'title' =>  'Firmware Success/Failure Email Alert'
        ],

        [
            'type'  =>  'notification[plan_purchase]',
            'title' =>  'New Plan Purchase'
        ],

        [
            'type'  =>  'notification[account_changes]',
            'title' =>  'Customer Account Changes'
        ],

        [
            'type'  =>  'notification[payout_ready]',
            'title' =>  'Payout Ready Notification'
        ],

        [
            'type'  =>  'notification[data_consumed]',
            'title' =>  'Usage Report Data Consumed'
        ],

        [
            'type'  =>  'notification[User_Wise]',
            'title' =>  'Usage Report User Wise '
        ],

        [
            'type'  =>  'notification[ap_wise]',
            'title' =>  'Usage Report AP Wise'
        ],

    ];

    public function oldstore(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $pdo = $user->id;
        $notificationSettingsArray = NotificationSettings::where('pdo_id', $pdo)->get();
        //Log::info($request);
        foreach ($notificationSettingsArray as $notificationSetting) {
            if (!isset($request->get('notification')[$notificationSetting->notification_type])) {
                $notificationSetting->update(['status' => 0]);
            }
        }

        if ($request->get('notification')){
            foreach ($request->get('notification') as $notificationType => $value) {
                $notificationSetting = NotificationSettings::where('pdo_id', $pdo)->where('notification_type', $notificationType)->first();
                if ($notificationSetting) {
                    $notificationSetting->update([
                        'status' => $value == "on" ? 1 : 0
                    ]);
                    $notificationSetting->save();
                } else {
                    NotificationSettings::create([
                        'pdo_id' => $pdo,
                        'status' => $value == "on" ? 1 : 0,
                        'notification_type' => $notificationType
                    ]);
                }
            }
        }
        return response()->json([
            'success' => true,
            'message' => 'Notification Settings updated successfully',
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $pdo = $user->id;

        // Existing notification settings for the user (PDO)
        $notificationSettingsArray = NotificationSettings::where('pdo_id', $pdo)->get();

        // Update existing notifications to off (status = 0) if they are not in the request
        foreach ($notificationSettingsArray as $notificationSetting) {
            if (!isset($request->get('email-type')[0][$notificationSetting->notification_type])) {
                $notificationSetting->update(['status' => 0, 'channel' =>null ,'recipient_id' => NULL, 'frequency' => null ,'weekly_day' =>null,
                    'date' =>null, 'time' =>null]);

            }
        }

        //Log::info('request_details :-------> ' , $request->all()); // Log the full request for debugging

        if ($request->get('email-type')) {
            foreach ($request->get('email-type') as $notifications) {
                foreach ($notifications as $notificationType => $value) {
                    $channels = $value['channel'] ?? NULL;
                    $recipients = $value['addrecipient'] ?? NULL;
                    $frequency = $value['frequency']['frequency'] ?? null;
                    $time = $value['frequency']['time'] ?? null;
                    $date = $value['frequency']['date'] ?? null;
                    $day = $value['frequency']['day'] ?? null;

                    $notificationSetting = NotificationSettings::where('pdo_id', $pdo)
                        ->where('notification_type', $notificationType)
                        ->first();

                    // Save or update the notification settings
                    if ($frequency === 'daily-with-time') {
                        $day = null; // No need for 'day' in daily notifications
                    } elseif ($frequency === 'weekly-with-time') {
                        if (!$day) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Weekly notification requires a day to be specified',
                            ], 400);
                        }
                    } elseif ($frequency === 'Custom-with-Date-and-Time') {
                        if (!$date || !$time) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Custom notification requires both date and time',
                            ], 400);
                        }
                    }

                    // Save or update the notification settings
                    if ($notificationSetting) {
                        $notificationSetting->update([
                            'status' => $request->get('status', 0),
                            'recipient_id' => json_encode($recipients) ? $recipients : NULL ,
                            'channel' => json_encode($channels),
                            'frequency' => $frequency,
                            'date' => $date,
                            'time' => $time,
                            'weekly_day' => $day,
                        ]);
                    } else {
                        NotificationSettings::create([
                            'pdo_id' => $pdo,
                            'notification_type' => $notificationType,
                            'status' => $request->get('status', 0),
                            'recipient_id' => json_encode($recipients), // Store as JSON array
                            'channel' => json_encode($channels),
                            'frequency' => $frequency,
                            'date' => $date,
                            'time' => $time,
                            'weekly_day' => $day, // Save day only for weekly notifications
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification Settings updated successfully',
        ]);
    }

    public function list()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User does not exist!'
            ], 200);
        }
        $notification = DB::table('notifications')->where('notifiable_id', $user->id)->orderBy('created_at', 'desc')->get();
        if ($notification->count() > 0) {

            return response()->json([
                'status' => true,
                'message' => 'Notification Detail',
                'notifications' => $notification->map(function ($noti) {
                    $data = json_decode($noti->data, true);
                    return [
                        'id' => $noti->id,
                        'time' => $noti->created_at,
                        'read_at' => $noti->read_at,
                        'data' => new NotificationResource(collect($data)),
                    ];
                })
            ], 200);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'No Notification exists',
                'notifications' => null
            ], 200);
        }
    }

    public function marksAsRead(Request $request)
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $user->unreadNotifications->markAsRead();
        return response()->json([
            'status' => true,
            'message' => 'Notifications read'
        ], 200);

    }

    public function possibleAlerts()
    {
        $user = Auth::user();
        if ($user->parent_id) {
            $parent = User::where('id', $user->parent_id)->first();
            $user = $parent;
        }
        $pdo = $user->id;
      /*  $notifications = NotificationSettings::where('pdo_id', $pdo)->orderBy('created_at', 'desc')->get();*/
        $desiredNotificationTypes = ['payout', 'location', 'router_up' ,'router_down', 'router_overload', 'router_status' ,'configuration_change', 'slow_network',
            'firmware_available', 'firmware_execution', 'firmware_success', 'plan_purchase' ,'account_changes' ,'payout_ready', 'data_consumed','User_Wise','ap_wise'];
        $desiredStatuses = ['1', '0'];
        $notifications = NotificationSettings::where('pdo_id', $pdo)
            ->whereIn('notification_type', $desiredNotificationTypes)
            ->whereIn('status', $desiredStatuses)
            ->orderBy('created_at', 'desc')
            ->get();
        //Log::info('details' . $notifications);
        return response()->json([
             'possibleAlerts' => $this->possibleAlertFields,
             'notification'=>$notifications
        ]);
    }

    public function recipients()
    {
        $user = Auth::user();
        if ($user) {
            // Fetch all users where parent_id matches the authenticated user's id
            $recipients = User::with('roles')->where('parent_id', $user->id)->orwhere('id', 1)->get();
            // Return the data as a JSON response for the API
            //Log::info('recipient list ---------------> ' . $recipients);
            return response()->json([
                'status' => 'success',
                'data' => $recipients
            ], 200); // 200 is the HTTP status code for success
        }
        // If the user is not authenticated, return an error response
        return response()->json([
            'status' => 'error',
            'message' => 'User not authenticated'
        ], 401); // 401 is the HTTP status code for unauthorized access
    }

}
