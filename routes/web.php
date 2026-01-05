<?php

use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\ZohoOauthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\PdoBankDetailsController;
use App\Http\Controllers\PdoAgreementDetailsController;
use App\Http\Controllers\ThresholdNotificationController;
use App\Http\Controllers\PayoutLogController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\NetworkProfileController;
use Illuminate\Http\Request;
use App\Http\Controllers\SwitchController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/user/accept-invitation/{parent}/{token}', [RegisterController::class, 'get']);

Route::resource('register', 'App\Http\Controllers\RegisterController');
// Route::get('/pdo-bank-list', [PdoBankDetailsController::class, 'index']);
Route::get('/pdo-bank-details', function () {
    return view('pdo-bank-list');
});

Route::get('/approved-payout', function () {
    return view('approved-payout-list');
});

Route::get('/pdo-agreement-details', function () {
    return view('pdo-agreement-list');
});

Route::get('/', function () {
    return redirect('login');
});

Route::get('/test/payment', function () {
    return view('test_payment');
});

Route::get('/login', function () {
    return view('login');
});

Route::get('/dashboard', function () {
    return view('dashboard-all-users');
});

Route::get('/inventory', function () {
    return view('inventory');
});

Route::get('/controler', function () {
    return view('controler');
});

Route::post('/inventory', [RouterController::class, 'getdetails'])->name('inventory.details');
Route::get('/switch', [SwitchController::class, 'index'])->name('switch.chasis');

Route::get('/inventory-dashboard', function(){
    return view('inventory-dashboard');
});

Route::get('/wifi-users', function () {
    return view('wifi-users');
});

Route::get('/location-owners', function () {
    return view('location-owners-v3');
});

Route::get('/distributor-plan', function () {
    return view('distributor.distributor-plan');
});

Route::get('/distributor-type', function () {
    return view('distributor.distributor-types');
});

Route::get('/distributor-account', function () {
    return view('distributor.distributor-account');
});

Route::get('/distributor-payouts', function () {
    return view('distributor.distributor-payouts');
});

Route::get('/distributor-payouts-details', function () {
    return view('distributor.distributor-payouts-details');
});

Route::get('/location-owners-v3', function () {
    return view('location-owners-v3');
});

Route::get('/location-owners-alt', function () {
    return view('location-owners');
});

Route::get('/locations', function () {
    return view('locations');
});

Route::get('/competitive-dashboard', function () {
    return view('competition-dashboard');
});

Route::get('/add-location', function () {
    return view('add-location');
});

Route::get('/edit-location', function () {
    return view('edit-location');
});

Route::get('/profile', function () {
    return view('profile');
});


Route::get('/iplog', function () {
    return view('iplog');
});

Route::get('/kernellog', function () {
    return view('kernellog');
});

Route::get('/msglog', function () {
    return view('msglog');
});

Route::get('/app-login-log', function () {
    return view('app-login-log');
});

Route::get('/internet-plans', function () {
    return view('internet-plans');
});

Route::get('/profile-internet-plans', function () {
    return view('profile-internet-plans');
});

Route::get('/payments', function () {
    return view('payments');
});

Route::get('/payouts', function () {
    return view('payouts');
});

Route::get('/pdo-payouts', function () {
    return view('pdo-payouts-v2');
});

Route::get('/pdo-payouts-dashboard', function () {
    return view('pdo-payouts-dashboard');
});

// For Pending PDO request
Route::get('/pdo-request', function () {
    return view('pdo-request-list');
});

Route::get('/pdo-payout-list', function () {
    return view('pdo-payout-list');
});

Route::get('/pdo-payouts-details', function () {
    return view('pdo-payouts-details');
});

Route::get('/sessions', function () {
    return view('sessions');
});

Route::get('/support', function () {
    return view('support');
});

Route::get('/network-settings', function () {
    return view('network-settings');
});

Route::get('/pdoa-plans', function () {
    return view('pdoa-plans');
});

Route::get('/monitor', function () {
    return view('monitoring');
});

Route::get('/wifi-monitoring', function () {
    return view('wifi-monitoring');
});

Route::get('/models', function () {
    return view('models');
});

Route::get('/settings', function () {
    return view('settings');
});


Route::get('/teams', function () {
    return view('teams');
});

Route::get('/roles', function () {
    return view('roles');
});

Route::get('/permissions', function () {
    return view('permissions');
});

Route::get('/invitations', function () {
    return view('invitations');
});
Route::get('/notification-settings', function () {
    return view('notification-settings');
});

Route::get('/show-notification', function () {
    return view('show-notification');
});

Route::get('/subscription-plan-details', function () {
    return view('subscription-plan-details');
});

Route::get('/sms-history ', function () {
    return view('sms-history');
});

Route::get('/credits-history ', function () {
    return view('credits-history');
});
Route::get('/used-credits-history ', function () {
    return view('used-credits-history');
});
Route::get('/used-sms-history ', function () {
    return view('used-sms-history');
});

//Route::get('/show-notification',[NotificationSettingsController::class ,'list']);


Route::get('/zone-profile', function () {
    return view('zone-profile');
});

Route::get('/add-zone-profile', function () {
    return view('add-zone-profile');
});

Route::get('/zone-internet-plans-voucher', function () {
    return view('internet-plans-vouchers');
});

Route::get('/all-vouchers', function () {
    return view('pdo-all-internet-plans-vouchers');
});

Route::get('/ap-settings', function () {
    return view('ap-settings');

});

Route::get('/global-payment-settings', function () {
    return view('global-payment-settings');

});
Route::get('/advertisements', function () {
    return view('banner');
});
Route::get('/adds', function () {
    return view('pdo-adds');
});

Route::get('/wifi-configuration-profiles', function () {
    return view('/wifi-configuration-profiles');
});

Route::get('/snmp-profiles', function () {
    return view('/snmp-profiles');
});

Route::get('/add-wifi-configuration-profile', function () {
    return view('/add-wifi-configuration-profile');

});

Route::get('/edit-wifi-configuration-profile', function () {
    return view('/edit-wifi-configuration-profile');

});

Route::get('/user-access-control', function () {
    return view('user-access-control');

});

/*Route::get('/banner', function () {
    return view('banner');
});*/

Route::get('/link/zoho', [ZohoOauthController::class, 'index'])->name('zoho.link');
Route::get('/link/zoho/callback', [ZohoOauthController::class, 'callback'])->name('zoho.callback');

Route::get('/threshold_notification', [ThresholdNotificationController::class, 'index']);
Route::get('/threshold_notification/list', [ThresholdNotificationController::class, 'list']);

// Route::middleware(['auth:sanctum', 'verified'])->group(function () {


    Route::get('/snmp-profile', function () {
        return view('snmp-profile');
    });
    Route::post('/snmp-profile', function (Request $request) {
        $id = $request->input('id');
        return view('snmp-profile', ['id' => $id]);
    })->name('snmp-profile.display');

    Route::get('/snmp-profiles-index', function () {
        return view('snmp-profiles-index');
    });


    Route::get('/ntp-profile', function () {
        return view('ntp-profile');
    });
    Route::post('/ntp-profile', function (Request $request) {
        $id = $request->input('id');
        return view('ntp-profile', ['id' => $id]);
    })->name('ntp-profile.display');
    
    Route::get('/ntp-profiles-index', function () {
        return view('ntp-profiles-index');
    });

    Route::get('/domainfilter-profile', function () {
        return view('domainfilter-profile');
    });
    Route::post('/domainfilter-profile', function (Request $request) {
        $id = $request->input('id');
        return view('domainfilter-profile', ['id' => $id]);
    })->name('domainfilter-profile.display');

    Route::get('/domainfilter-profiles-index', function () {
        return view('domainfilter-profiles-index');
    });

    Route::get('/macwhitelist-profile', function () {
        return view('macwhitelist-profile');
    });
    Route::post('/macwhitelist-profile', function (Request $request) {
        $id = $request->input('id');
        return view('macwhitelist-profile', ['id' => $id]);
    })->name('macwhitelist-profile.display');

    Route::get('/macwhitelist-profiles-index', function () {
        return view('macwhitelist-profiles-index');
    });


    Route::get('/qos-profile', function () {
        return view('qos-profile');
    });
    Route::post('/qos-profile', function (Request $request) {
        // return dd($request->all());
        $id = $request->input('id');
        return view('qos-profile', ['id' => $id]);
    })->name('qos-profile.display');
    
    Route::get('/qos-profiles-index', function () {
        return view('qos-profiles-index');
    });

    Route::get('/network-profile', function () {
        return view('network-profile');
    });
    Route::post('/network-profile', function (Request $request) {
        // return dd($request->all());
        $id = $request->input('id');
        return view('network-profile', ['id' => $id]);
    })->name('network-profile.display');

    // Route::get('/hotspot-profile', function () {
    //     return view('hotspot-profile');
    // });
    // Route::post('/hotspot-profile', function (Request $request) {
    //     // return dd($request->all());
    //     $id = $request->input('id');
    //     return view('hotspot-profile', ['id' => $id]);
    // })->name('hotspot-profile.display');






    Route::post('auth/login-as-pdo-owner/{id}', [LocationOwnerController::class, 'login_as_pdo_owner']);

    // Route::resource('network-profiles', NetworkProfileController::class);

    // Route::get('/network-profiles-index', function () {
    //     return view('network-profiles-index');
    // });

    // Route::get('/network-profiles-index', function () {
    //     return view('network-profiles-index');
    // });
    Route::get('/network-profiles-index/{tab}', function ($tab) {
        return view('network-profiles-index', ['tab' => $tab]);
    });

    Route::get('/print', function () {
        return view('pdf/contract');
    });

    
// });

require __DIR__ . '/auth.php';
