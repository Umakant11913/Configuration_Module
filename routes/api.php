<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CompetitionDashboardController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\NotificationAlertCronController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\InvitationsController;
use App\Http\Controllers\TeamsController;
use App\Http\Controllers\UserIPAccessLogController;
use App\Http\Controllers\CustomerProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InternetPlansController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationOwnerController;
use App\Http\Controllers\NetworkSettingsController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\PaymentOrdersController;
use App\Http\Controllers\PayoutTestController;
use App\Http\Controllers\PdoaPlanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WiFiOrdersController;
use App\Http\Controllers\WifiOtpController;
use App\Http\Controllers\WifiSessionController;
use App\Http\Controllers\WiFiUserController;
use App\Http\Controllers\WiFRouterController;
use App\Http\Controllers\ZohoOauthController;
use App\Http\Controllers\CaptiveController;
use App\Http\Controllers\AppLoginLogController;
use App\Http\Controllers\SessionLogController;
use App\Http\Controllers\PayoutLogController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\DistributorPlanController;
use App\Http\Controllers\DistributorTypesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\BrandingProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoamingController;
use App\Http\Controllers\InternetPlanVoucherController;
use App\Http\Controllers\PdoPaymentGatewayController;
use App\Http\Controllers\WifiConfigurationProfilesController;
use App\Http\Controllers\UserAccessController;
use \App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\UserGroupsController;
use App\Http\Controllers\MacGroupsController;
use App\Http\Controllers\PdoBankDetailsController;
use App\Http\Controllers\PdoAgreementDetailsController;
use App\Http\Controllers\ThresholdNotificationController; 
use App\Http\Controllers\SwitchController; 
use App\Http\Controllers\MqttServerController;
use Illuminate\Support\Facades\Http;
use PhpParser\JsonDecoder;
use App\Http\Controllers\NetworkProfileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['as' => 'api.'], function () {

    Route::get('/populate-wani', function () {
        \App\Jobs\ParseWaniProvidersJob::dispatch();
    });
    Route::get('/captive/forward', [CaptiveController::class, 'forward'])->name('captive.forward');
    Route::get('/show-service-error', [CaptiveController::class, 'showServiceError']);

    Route::get('/router-status-alert', [CronController::class, 'routerStatusCheck']);
    Route::get('/router-over-load', [CronController::class, 'routerOverload']);
    Route::get('/reset-sms-quota', [CronController::class, 'resetSmsQuota']);
    Route::get('/set-auto-renew',[CronController::class,'checkAutoRenewStatus']);
    Route::get('/subscription-end-alert',[CronController::class,'subscriptionEndAlert']);
    Route::get('/router-subscription-auto-renew',[CronController::class,'routerSubscriptionAutoRenewUpdate']);
    Route::get('/update-sms-quota',[CronController::class,'updateSmsCredits']);
    Route::get('/send-ads-summary',[CronController::class,'sendAdsSummary']);

    //Notification Cron-Api
    Route::get('/send-notification-alerts',[NotificationAlertCronController::class,'sendNotificationAlerts']);
    Route::get('/syncs/model-by/inventory-manager',[CronController::class,'modelByInventoryManager']);
    Route::get('/syncs/router/inventory-manager',[CronController::class,'syncRouterInventory']);
    Route::get('/syncs/routerslist',[CronController::class,'RouterInventoryList']);


    //Receive Data from mqtt    
    Route::post('/mqtt-heartbeat', [MqttServerController::class, 'store_heartbeat']);
    Route::post('/clients_connected', [MqttServerController::class, 'clients_connected']);
    Route::post('ap_config/update_single', [MqttServerController::class, 'updateApConfig_single_response']);
    // Route::post('ap_config/update', [MqttServerController::class, 'updateApConfig'])->name('ap_config.update');

    /*Route::get('/wifi-orders-update',[CronController::class,'wifiOdersUpdate']);*/

    Route::get('/mac2session', [RoamingController::class, 'index']);

    Route::post('auth/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login');

   Route::post('radius-client-authentication',  [WiFiUserController::class, 'radiusClientAuthentication'])
      ->name('radius-test');

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::get('/branding-profile-data', [BrandingProfileController::class, 'getOne']);

    Route::post('auth/login-as-pdo-owner/{id}', [LocationOwnerController::class, 'login_as_pdo_owner']);

    Route::post('auth/login/customer', [AuthenticatedSessionController::class, 'customer'])
        ->name('login');

    Route::post('auth/register', [RegisteredUserController::class, 'store'])
        ->name('register');

    Route::post('auth/forgot-password', [PasswordResetLinkController::class, 'customer'])
        ->name('forgot-password');

    Route::post('auth/customer/signup', [RegisteredUserController::class, 'store'])
        ->name('customer.signup');

    Route::post('auth/pmwani/register', [RegisteredUserController::class, 'register_pmwwani_user'])
        ->name('customer.pmwanisignup');

    Route::post('auth/customer/test-signup', [RegisteredUserController::class, 'test'])
        ->name('customer.signup.test');

    Route::get('media/{path}', [Controller::class, 'media'])
        ->where('path', '(.*)')
        ->name('media');


    Route::post('/wifilogin/customer/verify-otp', [WifiOtpController::class, 'verifyOtp']);
    Route::post('/wifilogin/customer/resend-otp', [WifiOtpController::class, 'resendOtp']);
    Route::post('/wifiuser/url-login', [RegisteredUserController::class, 'url_login']);
    Route::post('/wifiuser/login-as-wifiuser/{userId}', [RegisteredUserController::class, 'direct_login']);
    Route::post('/customer/set-language',[RegisteredUserController::class,'setLanguage']);
    Route::get('/customer/get-preffered-language',[RegisteredUserController::class,'getLanguage']);

    Route::middleware(['auth:api'])->get('/user', function (Request $request) {
        return $request->user();
    })->name('user');

    Route::group(['middleware' => 'auth:api'], function () {
    
        Route::post('ap_config/update', [MqttServerController::class, 'updateApConfig'])->name('ap_config.update');
        Route::post('/ip-logging/{router_key}', [WiFRouterController::class, 'ip_logging']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/apOnlineCount', [DashboardController::class, 'apOnlineCount']);
        Route::get('/getPayout/{period}', [DashboardController::class, 'getPayout']);
        Route::get('/top_data_usage/{period}', [DashboardController::class, 'top_data_usage']);
        Route::get('/top-data-usage', [DashboardController::class, 'top_data_usage_pdo']);
        Route::get('/top-users-location', [DashboardController::class, 'topUsersLocation']);
        Route::get('/top_users_location/{period}', [DashboardController::class, 'top_users_location']);
        Route::get('/top_selling_location/{period}', [DashboardController::class, 'top_selling_location']);
        Route::get('/top_daily_online/{period}', [DashboardController::class, 'top_daily_online']);
        Route::get('/top_data_client/{period}', [DashboardController::class, 'top_data_client']);
        Route::get('/top_paying_client/{period}', [DashboardController::class, 'top_paying_client']);
        Route::get('/location-with-zone', [DashboardController::class, 'locationAndZone']);
        Route::get('/load-sms-quota', [DashboardController::class, 'loadSmsQuota']);
        Route::get('/monthly-sms-quota', [DashboardController::class, 'monthlySmsQuota']);

        // Route::get('/pdo-bank-details/list', [PdoBankDetailsController::class, 'list']);

        // //graph calculation for distributor module total_user_wifi_used_session
        // Route::get('/total_user_wifi_used_session',[DashboardController::class,'total_user_wifi_used_session']);
        // //graph calculation for distributor module total_created_session
        // Route::get('/total_created_session',[DashboardController::class,'total_created_session']);
        // //graph calculation for live pdo percentage
        // Route::get('/live_pdo_uptime',[DashboardController::class,'live_pdo_uptime']);

        Route::get('/me', [PermissionsController::class, 'currentUser']);
        Route::get('/globalRoles', [RolesController::class, 'globalRoles']);
        Route::get('/roles', [RolesController::class, 'index']);
        Route::post('/roles', [RolesController::class, 'store']);
        Route::post('/roles/{role_id}', [RolesController::class, 'update']);
        Route::post('/delete-role/{role_id}', [RolesController::class, 'delete']);
        Route::post('/delete-team/{team_id}', [TeamsController::class, 'delete']);
        Route::post('/delete-invite/{invite_id}', [InvitationsController::class, 'delete']);
        Route::get('/roles/{id}', [RolesController::class, 'show']);
        Route::get('/permissions', [PermissionsController::class, 'index']);
        Route::post('/permissions', [PermissionsController::class, 'store']);
        Route::get('/permissions_list', [RolesController::class, 'permissions']);
        Route::get('/invitations', [InvitationsController::class, 'index']);
        Route::post('/invitations', [InvitationsController::class, 'store']);
        Route::get('/invitations/{user_id}', [InvitationsController::class, 'resendInvite']);
        Route::get('/permissions/{user_id}', [PermissionsController::class, 'permissionList']);
        Route::get('/teammate/{teammate_id}', [TeamsController::class, 'edit']);
        Route::post('/teammate-update/{user_id}', [TeamsController::class, 'update']);
        Route::get('/teams', [TeamsController::class, 'index']);


        Route::get('/models', [ModelController::class, 'index']);
        Route::post('/models', [ModelController::class, 'store']);
        Route::get('/models/{id}', [ModelController::class, 'edit']);
        Route::post('/model/{id}', [ModelController::class, 'update']);
        Route::get('/model-delete/{id}', [ModelController::class, 'destroy']); //delete - vilas
        Route::get('/model-suspend/suspend/{id}', [ModelController::class, 'suspend']); //suspend - vilas
        Route::get('/model-suspend/unsuspend/{id}', [ModelController::class, 'unsuspend']); //suspend - vilas
        Route::post('/firmware-file', [ModelController::class, 'firmwareStore']);
        Route::get('/uploaded-firmware-files/{modelId}', [ModelController::class, 'viewFile']);
        Route::post('/release-firmware-files/{fileId}', [ModelController::class, 'releaseFirmware']);
        Route::post('/update-firmware/{routerId}', [RouterController::class, 'updateFirmware']);
        Route::get('/router-model/{id}', [ModelController::class, 'getModelfromId']); //vilas
        Route::get('/ap-models/{id}', [ModelController::class, 'showApModel']);

        Route::post('/settings', [SettingsController::class, 'store']);
        Route::post('/wifi-session', [WiFiUserController::class, 'isActiveSession']);
        Route::post('/wifi-user-action', [WiFiUserController::class, 'wifiUserAction']); //suspend, unsuspend


        Route::get('/routers', [RouterController::class, 'index']);
        Route::get('/controler', [RouterController::class, 'controler_device']);
        Route::post('/router-retire', [RouterController::class, 'apRetire']);
        Route::post('/routers', [RouterController::class, 'store']);
        Route::get('/routers/{router}', [RouterController::class, 'show']);
        Route::delete('/routers/{router}', [RouterController::class, 'destroy']);
        Route::post('/assign-routers', [RouterController::class, 'assignRouter']);
        Route::post('/rebootAP/{routerId}', [RouterController::class, 'restartResetAP']);
        Route::get('getPdos', [RouterController::class, 'getPdos']);
        Route::get('getDistributors', [RouterController::class, 'getDistributors']);
        Route::post('active-router/{routerId}', [RouterController::class, 'activeRouter']);
        Route::post('deactive-router/{routerId}', [RouterController::class, 'deActiveRouter']);
        Route::get('routers-list', [RouterController::class, 'routerList']);
        Route::get('/location-router', [RouterController::class, 'locationRouter'])->name('location.router');

        Route::get('/customers', [CustomerController::class, 'index']);


        Route::get('/location-owners', [LocationOwnerController::class, 'index']);
        Route::get('/location-owners-with-payouts', [LocationOwnerController::class, 'index_with_payouts']);
        Route::post('/location-owners', [LocationOwnerController::class, 'store']);
        Route::post('/location-owners-status', [LocationOwnerController::class, 'statusUpdate']);
        Route::get('/location-owners/{user}', [LocationOwnerController::class, 'show']);
        Route::get('/selection-pdo-list', [LocationOwnerController::class, 'onloadPdo']); // for creating time selection pdo plan dropdown
        Route::post('/assign-pdo', [LocationOwnerController::class, 'assignPdo']);
        Route::get('/delete-pdo-acc/{id}', [LocationOwnerController::class, 'destroy']);
        Route::get('/location-owners-request-list', [LocationOwnerController::class, 'pdo_request_list']);
        Route::post('/location-owners-request-status/{id}', [LocationOwnerController::class, 'pdo_request_status']);
        Route::post('/add-credits', [LocationOwnerController::class, 'add_credits']);
        Route::post('/active-auto-renew/{pdo}', [LocationOwnerController::class, 'activeAutoRenew']);
        Route::post('/disable-auto-renew/{pdo}', [LocationOwnerController::class, 'disableAutoRenew']);
        Route::get('/subs-plan-details', [LocationOwnerController::class, 'subscriptionPlanDetails']);
        Route::get('/credit-history', [LocationOwnerController::class, 'creditHistory']);
        Route::get('/credit-history/used', [LocationOwnerController::class, 'usedCreditHistory']);
        Route::get('/sms-history', [LocationOwnerController::class, 'smsHistory']);
        Route::get('/sms-history/used', [LocationOwnerController::class, 'usedSmsHistory']);
        Route::get('/sms-count', [LocationOwnerController::class, 'smsCount']);
        Route::get('/credits-count', [LocationOwnerController::class, 'creditsCount']);

        // Bank route
        Route::get('/pdo-bank-details', [PdoBankDetailsController::class, 'index']);
        Route::post('/pdo-bank-details', [PdoBankDetailsController::class, 'store']);
        Route::get('/pdo-bank-details/{id}', [PdoBankDetailsController::class, 'show']);
        Route::post('/pdo-bank-details/{id}', [PdoBankDetailsController::class, 'update']);
        Route::post('/pdo-bank-details-status', [PdoBankDetailsController::class, 'updateStatus']);

        // Agreement route
        Route::get('/pdo-agreement-details', [PdoAgreementDetailsController::class, 'index']);
        Route::post('/pdo-agreement-details', [PdoAgreementDetailsController::class, 'store']);
        Route::get('/pdo-agreement-details/{id}', [PdoAgreementDetailsController::class, 'show']);
        Route::post('/pdo-agreement-details/{id}', [PdoAgreementDetailsController::class, 'update']);
        Route::post('/pdo-agreement-details-status', [PdoAgreementDetailsController::class, 'updateStatus']);


        // Distributor route
        Route::get('/distributor-account', [DistributorController::class, 'index']);
        Route::post('/distributor-account', [DistributorController::class, 'store']);
        Route::get('/distributor-account/{id}', [DistributorController::class, 'show']);
        Route::post('/distributor-account/{id}', [DistributorController::class, 'update']);
        Route::post('/distributor-account-status', [DistributorController::class, 'updateStatus']);
        Route::get('/distributor-pincode', [DistributorController::class, 'getPinCodes']);
        // check and verify uploaded files data
        Route::get('/distributor-verify-uploaded-files/{id}', [DistributorController::class, 'getUploadedFiles']);
        Route::post('/distributor-verify-uploaded-files/{id}', [DistributorController::class, 'verifyUploadedFiles']);
        // distributor plan route
        Route::get('/distributor-plans', [DistributorPlanController::class, 'index']); //index
        Route::get('/distributor-plans/{plan}', [DistributorPlanController::class, 'show']); //edit
        Route::get('/distributor-plans-details/{id}', [DistributorPlanController::class, 'planDetails']); //get plan details
        // Distributor types
        Route::get('/distributor-types', [DistributorTypesController::class, 'index']);
        Route::post('/distributor-types', [DistributorTypesController::class, 'store']);
        // Display list of child distributor
        Route::get('/distributor/list/{id}', [DistributorController::class, 'distributorChildList']);

        Route::get('/locations', [LocationController::class, 'index']);
        Route::get('/findLocation', [LocationController::class, 'getLocation']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::get('/locations/{location}', [LocationController::class, 'show']);
        Route::get('/locations-delete/{id}', [LocationController::class, 'destroy']); //delete

        Route::get('/locations/info/{location_id}', [LocationController::class, 'info']);
        Route::post('/location/update-private-network', [LocationController::class, 'update_private_network']);
        Route::get('/generate-key', [LocationController::class, 'generateLicenseKey']);

        //Competitive-Dashboard
        Route::post('/competition-dashboard', [CompetitionDashboardController::class, 'competitiveDashboard']);
        Route::get('/filter-Data', [CompetitionDashboardController::class, 'filterData']);
        Route::get('/top-pdoas', [CompetitionDashboardController::class, 'topPDOAS']);
        Route::post('/chart-data', [CompetitionDashboardController::class, 'chartData']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile/picture', [ProfileController::class, 'updateProfilePic']);
        Route::post('/profile', [ProfileController::class, 'store']);
        Route::post('/update-profile', [ProfileController::class, 'profileUpdate']);
        Route::post('/profile/basic', [ProfileController::class, 'basic']);
        Route::get('/subscription', [ProfileController::class, 'subscription']);


        Route::get('/profile-internet-plans', [InternetPlansController::class, 'profilePlanindex']);
        Route::post('/profile-internet-plan', [InternetPlansController::class, 'profilePlanStore']);
        Route::post('/profile-internet-plan/{id}', [InternetPlansController::class, 'profilePlanUpdate']);
        Route::get('/profile-internet-plans/{id}', [InternetPlansController::class, 'profilePlanindex']);

        Route::get('/internet-plans', [InternetPlansController::class, 'index']);
        Route::get('/internet-plans/{internet_plan}', [InternetPlansController::class, 'show']);
        Route::post('/internet-plans/list', [InternetPlansController::class, 'list']);
        Route::post('/internet-plans/create', [InternetPlansController::class, 'create']);
        Route::post('/internet-plans/update/{id}', [InternetPlansController::class, 'update']);
        Route::post('/suspend-pmwani-plan', [InternetPlansController::class, 'suspendPlan']);

        //Route for Internet Plan Vouchers
        Route::get('/internet-plans-vouchers', [InternetPlanVoucherController::class, 'index']);
        Route::get('/internet-plans-vouchers-detail', [InternetPlanVoucherController::class, 'showVouchersDetail']);
        Route::post('/internet-plans-vouchers', [InternetPlanVoucherController::class, 'store']);
        Route::get('/show-voucher-details', [InternetPlanVoucherController::class, 'showSingleVoucherDetails']);
        Route::get('/cancel-voucher', [InternetPlanVoucherController::class, 'cancelVoucher']);

        // Route For MAC Access Control
        Route::post('/mac-group', [MacGroupsController::class, 'store']);
        Route::get('/mac-groups', [MacGroupsController::class, 'index']);
        Route::post('/mac-group/{id}', [MacGroupsController::class, 'update']);
        Route::get('/mac-group-edit', [MacGroupsController::class, 'edit']);
        Route::post('/disable-macgroup', [MacGroupsController::class, 'disable']);
        Route::post('/delete-macgroup', [MacGroupsController::class, 'delete']);
        Route::post('/enable-macgroup', [MacGroupsController::class, 'enable']);

        // Route For Group
        Route::post('/group', [UserGroupsController::class, 'store']);
        Route::get('/groups', [UserGroupsController::class, 'index']);
        Route::get('/edit-group', [UserGroupsController::class, 'edit']);
        Route::post('/delete-group', [UserGroupsController::class, 'delete']);
        Route::post('/update-group/{id}', [UserGroupsController::class, 'update']);
        Route::get('/get-group', [UserGroupsController::class, 'addCheckbox']);

        // Route For User-Group
        Route::post('/user-group', [UserGroupsController::class, 'storeUser']);
        Route::get('/user-groups', [UserGroupsController::class, 'loadUsers']);
        Route::post('/delete-user-group', [UserGroupsController::class, 'deleteUser']);
        Route::get('/edit-user-group', [UserGroupsController::class, 'editUser']);
        Route::post('/update-user-group/{id}', [UserGroupsController::class, 'updateUser']);

        // Route For Guest-Group
        Route::post('guest', [UserGroupsController::class, 'storeGuest']);
        Route::get('guests', [UserGroupsController::class, 'loadGuests']);
        Route::post('/delete-guest', [UserGroupsController::class, 'deleteGuests']);
        Route::get('/edit-guest', [UserGroupsController::class, 'editGuests']);
        Route::post('/update-guest/{id}', [UserGroupsController::class, 'updateGuests']);


        Route::get('/pdoa-plans', [PdoaPlanController::class, 'index']);
        Route::get('/pdoa-plans/{plan}', [PdoaPlanController::class, 'show']);
        Route::get('/selection-pdo-plans', [PdoaPlanController::class, 'pdo_plan']); // for creating time selection pdo plan dropdown
        Route::post('pdoapl an-detail', [PdoaPlanController::class, 'getPlanDetail']);
        Route::get('/pdo-plan-delete/{id}', [PdoaPlanController::class, 'deletePdoPlan']);
        Route::post('/pdo-plan-suspend', [PdoaPlanController::class, 'suspendPdoPlan']);



        Route::group(['middleware' => 'role:admin'], function () {
            Route::post('/internet-plans', [InternetPlansController::class, 'store']);

            Route::post('/pdoa-plans', [PdoaPlanController::class, 'store']);

            // distributor plan route
            Route::post('/distributor-plans', [DistributorPlanController::class, 'store']); //add
            Route::post('/distributor-plans/{id}', [DistributorPlanController::class, 'update']); //update
        });

        Route::get('/wifi/sessions', [WifiSessionController::class, 'index']);
        Route::get('/wifi/session-log/{order_id}', [SessionLogController::class, 'order_session_log']);
        Route::get('/wifi/pdo-session-log', [SessionLogController::class, 'pdo_session_log']);
        Route::get('/zone-payment-gateway', [SessionLogController::class, 'pdo_payment_geteway']);
        Route::get('/wifi/iplogs', [UserIPAccessLogController::class, 'index']);
        Route::get('/wifi/kernellogs', [UserIPAccessLogController::class, 'kernellogs']);
        Route::get('/wifi/msglogs', [UserIPAccessLogController::class, 'msglogs']);
        Route::get('/wifi/app-login-logs', [AppLoginLogController::class, 'index']);

        Route::get('/wifi/statuses', [WifiSessionController::class, 'statuses']);

        Route::post('/support', [SupportController::class, 'store']);


        Route::group(['prefix' => 'customer'], function () {
            Route::get('/wifi/sessions', [WifiSessionController::class, 'forCustomer']);
            Route::get('/wifi/payments', [WiFiOrdersController::class, 'forCustomer']);
            Route::post('/profile', [CustomerProfileController::class, 'store']);
        });


        Route::post('/save-zone-plans/{id}', [BrandingProfileController::class, 'saveZonePlans']);
        // Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

        Route::get('/get-hotspot-profile', [BrandingProfileController::class, 'hotspotprofiles']);
        // Route::resource('network-profiles', NetworkProfileController::class);
        // Route::get('/profileTabs', [NetworkProfileController::class, 'getProfileTabs']);
        Route::get('/network-profiles-tabs', [NetworkProfileController::class, 'getProfileTabs']);     
        Route::get('/network-profiles-dropdowns', [NetworkProfileController::class, 'getProfileDropdowns']);    
        // Route::get('/network-profiles/toggle-status/{profileId}', [NetworkProfileController::class, 'toggleStatus']); 

        
    });

    Route::middleware('jwt.central')->group(function () {

        Route::get('network-profiles-index', [NetworkProfileController::class, 'index']);
        Route::get('network-profiles/{id}', [NetworkProfileController::class, 'show']);
        Route::post('network-profiles/store', [NetworkProfileController::class, 'store']);
        Route::delete('/network-profiles/{id}', [NetworkProfileController::class, 'destroy']);
        Route::get('/network-profiles/toggle-status/{profileId}', [NetworkProfileController::class, 'toggleStatus']); 

        Route::get('/zone-profile', [BrandingProfileController::class, 'index']);
        Route::post('/zone-profile', [BrandingProfileController::class, 'store']);
        Route::get('/zone-profile/{id}', [BrandingProfileController::class, 'show']);
        Route::post('/zone-profile/{id}', [BrandingProfileController::class, 'update']);
        Route::post('/delete-zone-profile/{id}', [BrandingProfileController::class, 'delete']);
        Route::post('/suspend-plan', [BrandingProfileController::class, 'suspendPlan']);

    });

    // Route::get('network-profiles-index', [NetworkProfileController::class, 'index']);
    
    // For User-Portal Internet Plans
    Route::group([
        'prefix' => 'internetplans'
    ], function ($router) {
        Route::post('/list', [InternetPlansController::class, 'list']);
        Route::post('/create', [InternetPlansController::class, 'create']);
        Route::post('/update/{id}', [InternetPlansController::class, 'update']);
        Route::post('/{id}', [InternetPlansController::class, 'oldindex']);
    });

    Route::group([
        'prefix' => 'vouchers'
    ], function ($router) {
        Route::post('/status', [InternetPlanVoucherController::class, 'checkVoucherStatus'])->name('voucher-status');
        Route::post('/send-voucher-code', [InternetPlanVoucherController::class, 'sendVoucherCode']);
    });

    Route::group([
        'prefix' => 'firmware'
    ], function ($router) {
        Route::get('/heartbeat/{key}/{secret}', [WiFRouterController::class, 'heartbeat']);
        Route::post('/heartbeat/{key}/{secret}', [WiFRouterController::class, 'heartbeat']);
        Route::get('/wifirouter/verify/{mac}', [WiFRouterController::class, 'verify']);
        Route::get('/wifirouter/config/{key}/{secret}', [WiFRouterController::class, 'config']);
        Route::get('/wifirouter/configV2/{key}/{secret}', [WiFRouterController::class, 'config_new']);
        Route::get('/wifirouter/verify/{verification_code}/{mac}', [\App\Http\Controllers\WiFRouterController::class, 'verify_router']);
        Route::post('/wifirouter/{router_key}/ip-logging', [WiFRouterController::class, 'ip_logging']);

        Route::get('/wifirouter/latest_version/{verification_code}/{mac}', [WiFRouterController::class, 'latest_version']);
        Route::get('/wifirouter/reset/{key}/{secret}', [WiFRouterController::class, 'reset']);
        Route::get('/wifirouter/reboot/{key}/{secret}', [WiFRouterController::class, 'reboot']);
        Route::get('/wifirouter/ssid_configuration/{key}/{secret}', [WiFRouterController::class, 'ssid_configuration']);
        Route::post('/custom_result', [WiFRouterController::class, 'custom_result']);
        Route::post('/wifirouter/{router_key}/ip-logging-kernel', [WiFRouterController::class, 'ip_logging_kernel']);

        // Route::get('/wifirouter/firmware/installer/{verification_code}/{model}/', [WifiController::class, 'get_installer']);

    });

    Route::group(['middleware' => 'role:admin'], function () {
        Route::group([
            'prefix' => 'network'
        ], function ($router) {
            Route::post('/settings', [NetworkSettingsController::class, 'index']);
            Route::post('/update', [NetworkSettingsController::class, 'update']);
            Route::post('/add', [NetworkSettingsController::class, 'create']);
            // Route::get('/wifirouter/config/{key}/{secret}', [WiFRouterController::class, 'config']);
        });
    });


    Route::group([
        'prefix' => 'wifirouter'
    ], function ($router) {
        Route::post('/add', [WiFRouterController::class, 'create']);
        Route::post('/update', [WiFRouterController::class, 'update']);
        Route::post('/list', [WiFRouterController::class, 'list']);
        Route::post('/{id}', [WiFRouterController::class, 'index']);
    });

    Route::group([
        'prefix' => 'wifilogin'
    ], function ($router) {
        Route::post('/autologin', [WiFiUserController::class, 'autologin']);
        // Route::post('/dologin', [WifiOtpController::class, 'login']);
        Route::post('/extend/{phone}', [WifiOtpController::class, 'extend_free']);
        Route::post('/verify-url', [WifiOtpController::class, 'verify_url']);
        Route::post('/generate-otp', [WifiOtpController::class, 'create']);
        Route::post('/verify-otp', [WifiOtpController::class, 'verify']);
        Route::post('/resend-otp', [WifiOtpController::class, 'resend']);
        Route::post('/generate-login', [WifiOtpController::class, 'generate_login']);
        Route::post('/info', [WiFiUserController::class, 'info']);
        Route::post('/verify-otp-status', [WifiOtpController::class, 'verify_otp_status']);
        Route::post('/planstatus', [WiFiUserController::class, 'planstatus']);
        Route::post('/send-order', [WiFiUserController::class, 'send_order']);
        Route::post('/send-order-v2', [WiFiUserController::class, 'send_order_v2']);
        Route::get('/log-session', [SessionLogController::class, 'create']);
        Route::get('/update-log-session', [SessionLogController::class, 'update']);
        Route::get('/process-all-logs', [SessionLogController::class, 'process_all_logs']);
        Route::get('/process-all-orders', [SessionLogController::class, 'process_all_orders']);

        Route::post('/send-free-order', [WiFiUserController::class, 'send_free_order']);
        Route::post('/send-login_url', [WiFiUserController::class, 'send_login_url']);

    });

    Route::group([
        'prefix' => 'payout'
    ], function ($router) {
        Route::get('/list', [PayoutLogController::class, 'list']);
        Route::get('/getPayout', [PayoutLogController::class, 'getPayout']);
        Route::get('/approved/{pdo_id}', [PayoutLogController::class, 'approved']);
        Route::get('/getTotalPdoData', [PayoutLogController::class, 'getTotalPdoData']);
        Route::get('/list/{id}', [PayoutLogController::class, 'pdo_list']);
        Route::get('/pdo/list', [PayoutLogController::class, 'list']);
        Route::get('/{payout_id}', [PayoutLogController::class, 'payout_detail']);
        Route::get('/proccessed/{payout_id}', [PayoutLogController::class, 'payout_proccessed']);
        // distributor payouts log
        Route::get('/distributor/list', [PayoutLogController::class, 'distributor_list']);
    });

    Route::group([
        'prefix' => 'orders'
    ], function ($router) {
        Route::post('/create', [PaymentOrdersController::class, 'create']);
        // Route::post('/list', [InternetPlansController::class, 'list']);
        Route::post('/{id}', [InternetPlansController::class, 'index']);
    });

    Route::group([
        'prefix' => 'wifi/order/'
    ], function ($router) {
        Route::get('/all', [WiFiOrdersController::class, 'all_orders']);
        Route::post('/create', [WiFiOrdersController::class, 'create']);
        Route::post('/verify', [WiFiOrdersController::class, 'verifyPayment']);
        Route::get('/update-payment-status', [WiFiOrdersController::class, 'updatePaymentStatus']);
        Route::post('/account/create', [WiFiOrdersController::class, 'accountOrder'])->middleware('auth:api');
        Route::get('/info/{id}', [WiFiOrdersController::class, 'info']);
        Route::post('/process-payment', [WiFiOrdersController::class, 'process_payment']);
        Route::post('/verify-and-process-payment', [WiFiOrdersController::class, 'process_payment_with_verify']);
        Route::post('/account/process-payment', [WiFiOrdersController::class, 'process_account_payment']);
        Route::post('/create-bsnl-order',[WiFiOrdersController::class,'createBsnlOrder']);
        Route::post('/order-status',[WiFiOrdersController::class,'orderStatus']);
        Route::get('/check-order-status',[WiFiOrdersController::class,'checkOrderStatus']);
    });

    Route::post('payment/{order_id}', [RazorpayController::class, 'payment']);

    Route::post('/zoho/webhook', [Controller::class, 'webhook']);

    Route::get('/test-payout', [PayoutTestController::class, 'test']);

    Route::post('/payment-gateway/initiate', [PaymentGatewayController::class, 'initiate']);
    Route::post('/payment-gateway/payment', [PaymentGatewayController::class, 'payment']);
    Route::post('/payment-gateway/resend-otp', [PaymentGatewayController::class, 'resendOtp']);
    Route::post('/payment-gateway/verify-otp', [PaymentGatewayController::class, 'verifyOtp']);
    Route::post('/notification-settings', [NotificationSettingsController::class, 'store']);
    Route::get('/notification-list', [NotificationSettingsController::class, 'list']);
    Route::get('/marks-read', [NotificationSettingsController::class, 'marksAsRead']);
    Route::get('/notification-settings-possible', [NotificationSettingsController::class, 'possibleAlerts']);
    Route::get('/recipients', [NotificationSettingsController::class, 'recipients']);

    Route::get('/load-location-zone', [NetworkSettingsController::class, 'loadZone']);
    Route::get('/load-ssid-control', [NetworkSettingsController::class, 'loadSsidControl']);
    Route::post('/ssid-control', [NetworkSettingsController::class, 'ssidControl']);
    Route::post('/global-payment-gateway-credentials', [PdoPaymentGatewayController::class, 'globalPaymentGateway']);
    Route::get('/load-pdo-payment-gateway', [PdoPaymentGatewayController::class, 'loadPdoPaymentGateway']);
    Route::get('/payment-gateway-credentials', [PdoPaymentGatewayController::class, 'checkPaymentGatewayCredentails']);
    Route::get('/validate-payment-gateway-keys', [PdoPaymentGatewayController::class, 'validateRazorpayKeys']);
//    Route::get('/payment-gateway-credentials', [PaymentGatewayController::class, 'pdoPaymentGatewayCredentials']);

    Route::get('/profiles', [WifiConfigurationProfilesController::class, 'index']);
    Route::post('/add-profile', [WifiConfigurationProfilesController::class, 'store']);
    Route::post('/delete-profile', [WifiConfigurationProfilesController::class, 'destroy']);
    Route::post('/disable-profile', [WifiConfigurationProfilesController::class, 'disable']);
    Route::post('/enable-profile', [WifiConfigurationProfilesController::class, 'enable']);
    Route::post('/publish-profile', [WifiConfigurationProfilesController::class, 'publish']);
    Route::get('/get-mac-groups', [WifiConfigurationProfilesController::class, 'getMacGroups']);
    Route::get('/get-guests', [WifiConfigurationProfilesController::class, 'getGuests']);
    Route::get('/get-groups', [WifiConfigurationProfilesController::class, 'getGroups']);
    Route::get('/get-users', [WifiConfigurationProfilesController::class, 'getUsers']);
    Route::get('/edit-profile', [WifiConfigurationProfilesController::class, 'edit']);
    Route::post('/update-profile/{id}', [WifiConfigurationProfilesController::class, 'update']);
    Route::get('/wifi-settings', [WifiConfigurationProfilesController::class, 'wifiSettings']);
    Route::get('/wifi-configuration-delete', [WifiConfigurationProfilesController::class, 'wifiSettings']);
    Route::post('/pm-wani-ssid-control', [WifiConfigurationProfilesController::class, 'savePmWaniSsidControl']);
    Route::get('/pm-wani-ssid-control', [WifiConfigurationProfilesController::class, 'getPmWaniSsidControl']);
    Route::get('wifi-status-graph', [DashboardController::class, 'wifiStatusGraph']);
    Route::get('/wifi-status-export', [DashboardController::class, 'exportWifiStatus']);
    Route::get('/wifi-client-graph', [DashboardController::class, 'wifiClientGraph']);
    Route::get('/ap-uptime-table', [DashboardController::class, 'apUptimeTable']);
    Route::get('/location-with-ap', [DashboardController::class, 'locationAP']);
    Route::get('location-uptime-table', [DashboardController::class, 'locationUptimeTable']);
    
    //bannnes
    /*Route::post('/banners', [BannerController::class, 'store']);
    Route::get('/banners', [BannerController::class, 'index']);
    Route::post('/banner-file', [BannerController::class, 'bannerStore']);*/

    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/suspended-banners', [BannerController::class, 'suspendedAds']);
    Route::post('/banners', [BannerController::class, 'store']);
    Route::get('/banners/{banner}', [BannerController::class, 'show']);
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy']);
    Route::post('/banner-file', [BannerController::class, 'bannerStore']);
    Route::post('/banner-file/{id}', [BannerController::class, 'bannerUpdate']);
    Route::get('/update-advertisement/{id}', [BannerController::class, 'getAdvertisementDetails']);
    Route::post('/delete-advertisement/{id}', [BannerController::class, 'deleteAdvertisement']);
    Route::get('/advertisement', [BannerController::class, 'advertisement']);
    Route::get('/add/advertisement', [BannerController::class, 'addCaptivePortal']);
    Route::post('/save-click', [BannerController::class, 'saveClick']);
    Route::get('/page-type', [BannerController::class, 'pageType']);
    Route::post('/user-impressions', [BannerController::class, 'userImpressions']);
    Route::post('/save-impression', [BannerController::class, 'userImpressionCount']);
    Route::get('/user-details', [BannerController::class, 'userDetails']);
    Route::post('/active-ads/{id}', [BannerController::class, 'activeAds']);

    Route::get('/threshold_notification/list', [ThresholdNotificationController::class, 'list']);
    Route::post('/threshold_notification/approved', [ThresholdNotificationController::class, 'approved']);
});
