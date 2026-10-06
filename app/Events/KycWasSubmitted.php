<?php

namespace App\Events;

use App\Models\Client;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The client finished the public KYC form. The save has committed; the
 * office briefing is a listener, not a line in the controller.
 */
class KycWasSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Client $client)
    {
    }
}
