<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fire-and-forget signal that new XDCC packs were identified. The Home page's
 * "Latest releases" Livewire component listens for this and re-renders, so
 * visitors see fresh data as soon as the minute sync notices a bot change
 * instead of waiting for a manual refresh.
 */
final class PacksUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function broadcastOn(): array
    {
        return [new Channel('packs')];
    }

    public function broadcastAs(): string
    {
        return 'PacksUpdated';
    }
}
