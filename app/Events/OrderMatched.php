<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;
    public int $buyerId;
    public int $sellerId;

    /**
     * Create a new event instance.
     */
    public function __construct(Trade $trade, int $buyerId, int $sellerId)
    {
        $this->trade = $trade;
        $this->buyerId = $buyerId;
        $this->sellerId = $sellerId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->buyerId),
            new PrivateChannel('user.' . $this->sellerId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        try {
            return [
                'trade' => [
                    'id' => $this->trade->id,
                    'symbol' => $this->trade->symbol,
                    'price' => (float) $this->trade->price,
                    'amount' => (float) $this->trade->amount,
                    'commission' => (float) $this->trade->commission,
                    'created_at' => $this->trade->created_at->toISOString(),
                ],
                'buy_order_id' => $this->trade->buy_order_id,
                'sell_order_id' => $this->trade->sell_order_id,
            ];
        } catch (\Exception $e) {
            \Log::error('OrderMatched: Error in broadcastWith', [
                'error' => $e->getMessage(),
                'trade_id' => $this->trade->id ?? 'unknown'
            ]);
            return [];
        }
    }
    
    /**
     * Determine if this event should be broadcast.
     */
    public function shouldBroadcast(): bool
    {
        return true;
    }
}
