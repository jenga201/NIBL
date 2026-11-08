<?php

namespace Tests\Unit\Events;

use App\Events\PacksUpdated;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

final class PacksUpdatedTest extends TestCase
{
    public function test_broadcasts_now_on_the_packs_channel(): void
    {
        $event = new PacksUpdated();

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame('PacksUpdated', $event->broadcastAs());
        $this->assertCount(1, $event->broadcastOn());
        $this->assertInstanceOf(Channel::class, $event->broadcastOn()[0]);
        $this->assertSame('packs', $event->broadcastOn()[0]->name);
    }
}
