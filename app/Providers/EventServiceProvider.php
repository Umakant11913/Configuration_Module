<?php

namespace App\Providers;

use App\Events\PdoAddCreditsEvents;
use App\Events\PdoAssignEvent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        \App\Events\PdoLocationEvent::class => [
            \App\Listeners\SendPdoLocationEmailListeners::class,
        ],

        \App\Events\PdoPayoutEvent::class => [
            \App\Listeners\SendPdoPayoutEmailListeners::class,
        ],

        \App\Events\PdoRouterDownEvent::class => [
            \App\Listeners\SendPdoRouterDownListeners::class,
        ],

        \App\Events\PdoRouterUpEvent::class => [
            \App\Listeners\SendPdoRouterUpListeners::class,
        ],

        \App\Events\PdoRouterOverLoadEvent::class => [
            \App\Listeners\SendPdoRouterOverLoadListeners::class,
        ],

        \App\Events\UserIPAccessLogEvent::class => [
            \App\Listeners\UserIPAccessLogListener::class,
        ],
        \App\Events\PdoSmsQuotaEvent::class => [
            \App\Listeners\PdoSmsQuotaListeners::class,
        ],
        \App\Events\PdoAssignEvent::class => [
            \App\Listeners\PdoAssignListeners::class,
        ],
        \App\Events\PdoAddCreditsEvents::class => [
            \App\Listeners\pdoAddCreditsListeners::class,
        ],
        \App\Events\PdoAddSmsEvents::class => [
            \App\Listeners\pdoAddSmsListeners::class,
        ],
        \App\Events\PdoLowCreditsEvent::class => [
            \App\Listeners\pdoLowCreditsListener::class,
        ],
        \App\Events\PdoSubscriptionEndAlertEvent::class => [
            \App\Listeners\PdoSubscriptionEndListener::class,
        ],
        \App\Events\PdoUsedSmsQuotaEvents::class => [
            \App\Listeners\pdoUsedSmsQuotaListeners::class,
        ],
        \App\Events\PdoRouterAutoRenewEvent::class => [
            \App\Listeners\PdoRouterAutoRenewListener::class,
        ],
        \App\Events\PdoSendAdsSummaryEvent::class => [
            \App\Listeners\PdoSendAdsSummaryListeners::class,
        ],

        \App\Events\SendPayoutEmailAlertEvent::class => [
            \App\Listeners\SendPayoutEmailAlertListeners::class,
        ],

        \App\Events\SendLocationEmailAlertEvent::class => [
            \App\Listeners\SendLocationEmailAlertListeners::class,
        ],

        \App\Events\SendRouterUpEmailAlertEvent::class => [
            \App\Listeners\SendRouterUpEmailAlertListeners::class,
        ],
        \App\Events\SendRouterDownEmailAlertEvent::class => [
            \App\Listeners\SendRouterDownEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendRouterOverLoadEmailAlertEvent::class => [
            \App\Listeners\SendRouterOverloadEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendfirmwareUpdateEmailAlertEvents::class => [
            \App\Listeners\SendfirmwareUpdateEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendConfigChangesEmailAlertEvent::class => [
            \App\Listeners\SendConfigChangesEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendPlanPurchaseEmailAlertEvent::class => [
            \App\Listeners\SendPlanPurchaseEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendAccountChangesEmailAlertEvent::class => [
            \App\Listeners\SendAccountChangesEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendRouterSlowNetworkEmailAlertEvent::class => [
            \App\Listeners\SendRouterSlowNetworkEmailAlertListeners::class, // Create this separate listener
        ],


        \App\Events\SendfirmwareExecutionEmailAlertEvents::class => [
            \App\Listeners\SendfirmwareExecutionEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendPayoutReadyEmailAlertEvents::class => [
            \App\Listeners\SendPayoutReadyEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendUserReportEmailAlertEvents::class => [
            \App\Listeners\SendUserReportEmailAlertListeners::class, // Create this separate listener
        ],

        \App\Events\SendApWiseReportEmailAlertEvents::class => [
            \App\Listeners\SendApWiseReportEmailAlertListeners::class, // Create this separate listener
        ],

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
