<?php

namespace App\Listeners;

use App\Events\KycWasSubmitted;
use App\Services\OfficeBriefing;
use Illuminate\Events\Dispatcher;

/**
 * The one map from a fact in the ERP to an office briefing.
 *
 * A new event is one line here plus a method. Controllers only dispatch;
 * they never know the inbox exists.
 */
class OfficeBriefingSubscriber
{
    public function __construct(private OfficeBriefing $briefing)
    {
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            KycWasSubmitted::class => 'onKycSubmitted',
        ];
    }

    public function onKycSubmitted(KycWasSubmitted $event): void
    {
        $this->briefing->kycSubmitted($event->client);
    }
}
